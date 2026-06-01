// Imported from the db-connector package which re-exports Playwright's test
// + expect. Keeps existing assertions working unchanged AND lets future
// specs in this file opt in to the `db` fixture for DB assertions.
import { expect, test } from '@ochorocho/playwright-db-connector';
import type { Page } from '@playwright/test';

/**
 * Backend smoke tests for the revealjs_editor extension.
 *
 * Logs in as the seeded `admin` user (Tests/playwright/fixtures/csv/be_users.csv)
 * with the cleartext password "Password.1" — the same argon2id hash is used
 * for both the admin and editor seed users.
 *
 * Targets the seeded "Lets Present This" page (uid 11) which carries the
 * doktype=1731 reveal.js subschema, the `the-caemp` theme value, and five
 * tt_content slide rows — so the BE assertions exercise the full
 * extension-managed surface: page subschema TCA, the Reveal Presentation
 * tab, the theme dropdown, and the slide list in the page module.
 */

const ADMIN = { username: 'admin', password: 'Password.1' };
const PRESENTATION_PAGE_UID = 11;
const PRESENTATION_PAGE_TITLE = 'Lets Present This';

/**
 * The seeded cover slide on uid 11 (CType=revealjs_slide_cover, sorting=32).
 * Used by the slide-editor smoke test below to assert the per-slide TCA
 * surface (Slide / Background / Auto-animate tabs + bg_image FAL picker).
 */
const COVER_SLIDE_UID = 115;

/**
 * The seeded code slide on uid 11 (CType=revealjs_slide_code, sorting=768).
 * Carries inline-IRRE `tx_revealjseditor_codeblock` rows — see
 * Tests/playwright/fixtures/csv/tx_revealjseditor_codeblock.csv. Used by
 * the BE FormEngine + visual_editor preview Monaco mount tests below.
 */
const CODE_SLIDE_UID = 116;

/**
 * Log into the TYPO3 v14 backend. The form uses three fields: `username`,
 * `p_field` (cleartext password — captured client-side and hashed into
 * `userident` before POST). Filling both fields and submitting the form is
 * enough; the bundled JS handles the rest.
 */
async function login(page: Page): Promise<void> {
    await page.goto('/typo3/login');
    await page.locator('#t3-username').fill(ADMIN.username);
    await page.locator('#t3-password').fill(ADMIN.password);
    await page.locator('#typo3-login-form').evaluate(
        (form: HTMLFormElement) => form.requestSubmit(),
    );
    // After successful auth TYPO3 redirects out of /typo3/login. The BE
    // shell loads a lot of JS modules and the `load` event sometimes
    // doesn't fire within Playwright's default wait — `domcontentloaded`
    // is enough for the topbar to render. The admin-only "Clear cache"
    // button is the post-login marker (also confirms admin role).
    await page.waitForURL((url) => !url.pathname.includes('/login'), {
        timeout: 15_000,
        waitUntil: 'domcontentloaded',
    });
    await expect(page.getByRole('button', { name: 'Clear cache' })).toBeVisible();
}

test.describe('TYPO3 backend', () => {
    test('login form renders with the seeded sitename in the title', async ({ page }) => {
        const response = await page.goto('/typo3/login');
        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle(/TYPO3 CMS Login/);
        await expect(page.locator('#t3-username')).toBeVisible();
        await expect(page.locator('#t3-password')).toBeVisible();
    });

    test('admin/Password.1 logs in successfully', async ({ page }) => {
        await login(page);
        // After login the topbar shows the seeded sitename + version. The
        // project name comes from the `--project-name` flag we pass to
        // `typo3 setup` in setup-typo3.sh ("revealjs-editor").
        await expect(page.getByRole('link', { name: /revealjs-editor/ })).toBeVisible();
    });

    test('the page tree lists the seeded reveal.js page', async ({ page }) => {
        await login(page);

        // Open the Web > Layout module focused on uid 11. The page tree
        // lazy-loads, so we don't assert on the tree DOM directly — the
        // breadcrumb of the focused page module is the stable signal that
        // TYPO3 found the page in the seeded `db` database and routed to it.
        await page.goto(`/typo3/module/web/layout?id=${PRESENTATION_PAGE_UID}`);
        const moduleFrame = page.frameLocator('iframe').first();
        await expect(
            moduleFrame.getByRole('button', { name: PRESENTATION_PAGE_TITLE }),
        ).toBeVisible({ timeout: 15_000 });
    });

    test('opening the page edit form shows the Reveal Presentation tab + theme select', async ({ page }) => {
        await login(page);

        // Direct-link to the page-properties edit form for uid 11. FormEngine
        // renders inside the BE module shell's content iframe, so all the
        // form-state assertions live behind frameLocator.
        const editUrl = `/typo3/record/edit?edit[pages][${PRESENTATION_PAGE_UID}]=edit&returnUrl=/typo3/module/web/layout`;
        await page.goto(editUrl);

        const formFrame = page.frameLocator('iframe').first();
        const revealTab = formFrame.getByRole('tab', { name: /Reveal Presentation/i });
        await expect(revealTab).toBeVisible({ timeout: 15_000 });

        await revealTab.click();

        // The theme dropdown is exposed as a combobox with an accessible
        // name composed by FormEngine ("Theme [tx_revealjseditor_theme]").
        // Matching on the column name in brackets is the most stable
        // signal that the TCA select got rendered for the right column.
        const themeSelect = formFrame.getByRole('combobox', {
            name: /\[tx_revealjseditor_theme\]/,
        });
        await expect(themeSelect).toHaveValue('the-caemp');
        // 14 stock + 1 custom (`the-caemp`) = 15 themes registered.
        await expect(themeSelect.getByRole('option')).toHaveCount(15);
    });

    test('the page module shows the five seeded tt_content slides', async ({ page }) => {
        await login(page);
        await page.goto(`/typo3/module/web/layout?id=${PRESENTATION_PAGE_UID}`);

        // The page-content iframe renders each tt_content row as a
        // <fieldset role="group"> with an `id=<uid> - <title> - <CType>`
        // accessible name. Seed has five rows in colPos 0: cover (115),
        // two text slides (105, 112), one textmedia slide (117), and
        // one code slide (116) — see
        // Tests/playwright/fixtures/csv/tt_content.csv.
        const moduleFrame = page.frameLocator('iframe').first();
        const slides = moduleFrame.getByRole('group', { name: /^id=\d+/ });
        await expect(slides).toHaveCount(5, { timeout: 15_000 });
    });
});

/**
 * Slide editor — opens the FormEngine edit view for the seeded cover slide
 * (tt_content uid 115, CType=revealjs_slide_cover) and asserts the per-slide
 * surface registered in `Configuration/TCA/Overrides/tt_content.php`:
 *
 *   - the three slide-options tabs (Slide / Background / Auto-animate),
 *   - a representative TCA column inside each tab (proves the foreach
 *     loop in tt_content.php registered the column AND the showitem
 *     palette wired it into the form),
 *   - the FAL picker on `tx_revealjseditor_bg_image` (proves the
 *     `type=file` upgrade landed: a varchar input would have no
 *     "Create new relation" / "Select & upload files" buttons),
 *   - the RTE-bound bodytext (richtextConfiguration='revealjs' — proves
 *     the columnsOverrides path took effect).
 */
test.describe('TYPO3 backend — slide editor', () => {
    test('cover slide edit form exposes the three reveal.js slide tabs + bg_image FAL picker', async ({ page }) => {
        await login(page);

        // Direct-link to FormEngine for tt_content uid 115. The returnUrl
        // value is irrelevant for the assertions below — TYPO3 just
        // requires the parameter to be set.
        const editUrl = `/typo3/record/edit?edit[tt_content][${COVER_SLIDE_UID}]=edit&returnUrl=/typo3/module/web/layout`;
        await page.goto(editUrl);

        // FormEngine lives inside the BE module shell's content iframe.
        const formFrame = page.frameLocator('iframe').first();

        // Wait for the edit form to be ready: the heading carries the
        // record's tt_content `header` ("The Cämp" — seeded for uid 115).
        await expect(
            formFrame.getByRole('heading', { level: 1, name: 'The Cämp' }),
        ).toBeVisible({ timeout: 15_000 });

        // ----- The three reveal.js slide tabs are present -----------------
        const slideTab = formFrame.getByRole('tab', { name: 'Slide' });
        const backgroundTab = formFrame.getByRole('tab', { name: 'Background' });
        const animateTab = formFrame.getByRole('tab', { name: 'Auto-animate' });
        await expect(slideTab).toBeVisible();
        await expect(backgroundTab).toBeVisible();
        await expect(animateTab).toBeVisible();

        // ----- Slide tab: transition select + speaker notes textarea ------
        await slideTab.click();
        // The transition combobox carries the column name in its accessible
        // name (FormEngine convention: "Label [column]"). The seeded value
        // is empty (= "Inherit from presentation") so we just check it's
        // wired and that the empty-option label is the inherit text.
        const transitionSelect = formFrame.getByRole('combobox', {
            name: /\[tx_revealjseditor_transition\]/,
        });
        await expect(transitionSelect).toBeVisible();
        // 6 reveal.js values + the inherit-empty entry = 7 options.
        await expect(transitionSelect.getByRole('option')).toHaveCount(7);
        // Speaker notes — type=text in TCA → <textarea> with column-named id.
        await expect(
            formFrame.locator('textarea[data-formengine-input-name*="tx_revealjseditor_slide_notes"]'),
        ).toBeAttached();

        // ----- Background tab: bg_color input + bg_image FAL picker -------
        await backgroundTab.click();
        // bg_color is type=input → plain text field.
        await expect(
            formFrame.locator('input[data-formengine-input-name*="tx_revealjseditor_bg_color"]'),
        ).toBeVisible();

        // The FAL picker for bg_image. FAL fields render as a "group"
        // labelled with the column name in brackets, containing the two
        // standard relation buttons. If bg_image were still a varchar
        // input these wouldn't exist.
        const bgImageGroup = formFrame.getByRole('group', {
            name: /\[tx_revealjseditor_bg_image\]/,
        });
        await expect(bgImageGroup).toBeVisible();
        await expect(
            bgImageGroup.getByRole('button', { name: /Create new relation/i }),
        ).toBeVisible();
        await expect(
            bgImageGroup.getByRole('button', { name: /Select & upload files/i }),
        ).toBeVisible();

        // ----- Auto-animate tab: the three checkboxes (toggles) -----------
        await animateTab.click();
        // The auto-animate toggles render as <input type="checkbox"> (the
        // checkboxToggle renderType wraps them in styled siblings). The
        // hidden _hr backing input keeps the data-formengine-input-name
        // attribute on the visible toggle.
        await expect(
            formFrame.locator('input[data-formengine-input-name*="tx_revealjseditor_anim_enabled"]'),
        ).toBeAttached();
        await expect(
            formFrame.locator('input[data-formengine-input-name*="tx_revealjseditor_anim_restart"]'),
        ).toBeAttached();
        await expect(
            formFrame.locator('input[data-formengine-input-name*="tx_revealjseditor_anim_unmatched"]'),
        ).toBeAttached();
    });

    test('code slide inline-IRRE code field mounts Monaco in the BE FormEngine', async ({ page }) => {
        // The `tx_revealjseditor_codeblock.code` column uses our custom
        // `renderType=monacoEditor` (registered in ext_localconf.php +
        // implemented in Classes/Form/Element/MonacoEditorElement.php).
        // The BE form emits `<typo3-monaco-editor>` containing a hidden
        // `<textarea name="data[…][…][…][code]" hidden>`; the JS module
        // (Resources/Public/JavaScript/MonacoFormEngineElement.js) mounts
        // Monaco inside and writes its value back into the textarea on
        // every change so the standard form-save POST picks it up. This
        // test pins the contract.
        await login(page);

        const editUrl = `/typo3/record/edit?edit[tt_content][${CODE_SLIDE_UID}]=edit&returnUrl=/typo3/module/web/layout`;
        await page.goto(editUrl);

        const formFrame = page.frameLocator('iframe').first();

        // Wait for the edit form to be ready.
        await expect(
            formFrame.locator('input[data-formengine-input-name*="tx_revealjseditor_codeblock"][data-formengine-input-name$="[headline]"]').first(),
        ).toBeAttached({ timeout: 20_000 });

        // The custom element wraps the hidden form textarea.
        await expect(
            formFrame.locator('typo3-monaco-editor').first(),
        ).toBeAttached({ timeout: 20_000 });

        // The hidden textarea (FormEngine's POST carrier) is still present
        // inside the custom element with the standard data[…] name.
        await expect(
            formFrame.locator('typo3-monaco-editor textarea[name*="tx_revealjseditor_codeblock"][name$="[code]"]').first(),
        ).toBeAttached();

        // Monaco's `.monaco-editor` root div appears inside the custom
        // element after the bundle loads + the editor mounts. networkidle
        // is the cheap proxy for "vendor bundles done loading".
        await page.waitForLoadState('networkidle');
        await expect(
            formFrame.locator('typo3-monaco-editor .monaco-editor').first(),
        ).toBeAttached({ timeout: 30_000 });

        // CodeMirror must NOT appear (we deliberately replaced
        // renderType=codeEditor; a regression would reintroduce it).
        await expect(
            formFrame.locator('typo3-t3editor-codemirror'),
        ).toHaveCount(0);
    });

    test('visual_editor preview iframe mounts Monaco editors (editable mode) per inline-IRRE code block', async ({ page }) => {
        // Contract pinned: the FE URL with ?editMode=1 + a logged-in admin
        // produces visual_editor's BE module shell with a preview iframe;
        // the same RevealMonacoMount.js that runs for anonymous viewers
        // runs in the iframe too — the template stamps `data-editable="1"`
        // on each `<pre data-monaco>`, which flips Monaco from read-only
        // to editable + wires onDidChangeModelContent into
        // visual_editor's `dataHandlerStore.setData(...)` so the BE
        // toolbar save persists via TYPO3's DataHandler.
        //
        // We don't assert Tab/IME/typing behaviour here: visual_editor's
        // preview iframe uses a selection-overlay model that blocks
        // direct clicks on iframe content until the content element has
        // been activated through its own UI — so simulating keyboard
        // input via Playwright is brittle. Editor input correctness is
        // Monaco's own well-tested concern; manual verification suffices.
        await login(page);

        // Assert the FE URL resolves before probing frames — surfaces
        // schema/site-config regressions as a clear 4xx instead of as a
        // missing iframe several lines down.
        const response = await page.goto('/subpage-germany/lets-present-this?editMode=1');
        expect(
            response?.ok(),
            `FE URL must resolve, got HTTP ${response?.status()}`,
        ).toBeTruthy();

        // Wait for visual_editor's preview iframe to attach. Match by the
        // parent's `src` attribute (Locators can't peek across frame
        // boundaries via `has:` — Frames are separate documents — but the
        // iframe element's own src lives on the parent and carries the
        // slug). Using a Locator + `toBeAttached` auto-retries via
        // Playwright's web-first assertions, replacing the brittle
        // `waitForLoadState('networkidle')` (TYPO3 BE shell polls, so the
        // network never goes idle) and the one-shot `page.frame({ url })`
        // (returns null synchronously if the iframe attaches a tick later)
        // of the previous incarnation of this test.
        const previewIframe = page.locator('iframe[src*="lets-present-this"]');
        await expect(previewIframe).toBeAttached({ timeout: 15_000 });
        const previewFrame = previewIframe.contentFrame();

        // Editable blocks rendered by Container/Code.html's single-shape
        // branch with data-editable="1". Web-first `toBeAttached` auto-waits.
        await expect(
            previewFrame.locator('.reveal pre[data-monaco][data-editable="1"]').first(),
        ).toBeAttached({ timeout: 15_000 });

        // Each block has Monaco mounted inside on DOMContentLoaded —
        // `.monaco-editor` is Monaco's root class.
        await expect(
            previewFrame.locator('.reveal pre[data-monaco][data-editable="1"] .monaco-editor').first(),
        ).toBeAttached({ timeout: 30_000 });

        // Wrappers carry the mount-marker after the mount script promotes
        // them. `not.toHaveCount(0)` auto-retries; the previous one-shot
        // `.count()` did not.
        await expect(
            previewFrame.locator('.reveal pre[data-monaco][data-monaco-mounted="1"]'),
        ).not.toHaveCount(0);
    });

    test('cover slide bodytext mounts the trimmed CKEditor 5 (no schema-cannot-register-item-twice)', async ({ page }) => {
        // Capture browser console errors. The bug we're guarding against
        // surfaces as `schema-cannot-register-item-twice {"itemName":"heading2"}`
        // — CKEditor's heading plugin refuses to register the same model
        // name twice, which happens when a custom RTE preset imports
        // EXT:rte_ckeditor/Configuration/RTE/Default.yaml AND re-declares
        // `editor.config.heading.options`. TYPO3's YAML import-merger
        // appends sequential lists rather than replacing them, so the
        // resolved config has heading2 listed twice. Result: CKEditor
        // throws inside an unhandled promise and the textarea stays
        // unenhanced — the editor never mounts.
        const consoleErrors: string[] = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });
        page.on('pageerror', (err) => consoleErrors.push(`pageerror: ${err.message}`));

        await login(page);

        const editUrl = `/typo3/record/edit?edit[tt_content][${COVER_SLIDE_UID}]=edit&returnUrl=/typo3/module/web/layout`;
        await page.goto(editUrl);

        const formFrame = page.frameLocator('iframe').first();
        await expect(
            formFrame.getByRole('heading', { level: 1, name: 'The Cämp' }),
        ).toBeVisible({ timeout: 15_000 });

        // CKEditor 5 mounts a `<div class="ck ck-editor">` wrapper next to
        // the bodytext <textarea>. Its presence confirms the editor finished
        // initialising (which it can't if the schema registration throws).
        await expect(
            formFrame.locator('.ck.ck-editor').first(),
        ).toBeVisible({ timeout: 20_000 });

        // The trimmed preset's toolbar should hold our 9 items (heading
        // dropdown, separators, bold, italic, link, bullet/numbered list,
        // removeFormat). Lower-bound the count to flag the
        // "Default.yaml-imported, fat duplicated toolbar" regression which
        // would push the count well above 20.
        const toolbarButtons = formFrame.locator('.ck.ck-toolbar button.ck-button');
        const buttonCount = await toolbarButtons.count();
        expect(buttonCount).toBeGreaterThan(0);
        expect(buttonCount).toBeLessThan(20);

        // No CKEditor schema registration error must have fired.
        const schemaError = consoleErrors.find((e) =>
            e.includes('schema-cannot-register-item-twice'),
        );
        expect(schemaError, `unexpected console error:\n${schemaError ?? ''}`).toBeUndefined();
    });
});
