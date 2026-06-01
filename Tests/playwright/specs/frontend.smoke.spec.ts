// Imported from the db-connector package which re-exports Playwright's test
// + expect. Keeps existing assertions working unchanged AND lets future
// specs in this file opt in to the `db` fixture for DB assertions.
import { expect, test } from '@ochorocho/playwright-db-connector';

/**
 * Frontend smoke tests for the revealjs_editor extension.
 *
 * Targets the seeded `Lets Present This` page (uid 11, slug
 * /subpage-germany/lets-present-this) which has 3 slides per the per-table
 * CSV fixtures in Tests/playwright/fixtures/csv/.
 */
test.describe('reveal.js page rendering', () => {
    const PRESENTATION_URL = '/subpage-germany/lets-present-this';

    test('the deck wrapper renders five sections inside .reveal .slides', async ({ page }) => {
        const response = await page.goto(PRESENTATION_URL);
        expect(response?.status()).toBe(200);

        await expect(page).toHaveTitle(/RevealJS/i);
        await expect(page.locator('div.reveal')).toHaveCount(1);
        // Seed has 5 rows in colPos 0 on pid=11: cover (115), two text
        // slides (112, 105), one textmedia slide (117), and one code
        // slide (116) — see Tests/playwright/fixtures/csv/tt_content.csv.
        await expect(page.locator('.reveal .slides > section')).toHaveCount(5);
    });

    test('reveal.js initialises in the browser (body gets reveal-viewport class)', async ({ page }) => {
        await page.goto(PRESENTATION_URL);

        // Body class is added by reveal.js on init. Wait for it.
        await expect(page.locator('body.reveal-viewport')).toBeVisible();

        // Exactly one slide should be marked .present after init.
        await expect(page.locator('.reveal .slides > section.present')).toHaveCount(1);

        // …and that slide must be *visible*, not just classed. Reveal.js's
        // layout pass applies `display:none` to any slide whose distance
        // from the current is >= viewDistance — and viewDistance:0 hides
        // even the current one. Asserting visibility guards against the
        // class indicator passing while the deck is actually blank
        // (regression check for missing/zero seed values on numeric TCA
        // options like tx_revealjseditor_viewdistance, where the schema
        // default is 0 regardless of the TCA `default` key).
        await expect(page.locator('.reveal .slides > section.present')).toBeVisible();

        // .reveal element gains the `ready` class when init completes.
        await expect(page.locator('.reveal.ready')).toBeVisible();
    });

    test('per-slide attributes from the ViewHelper land on the section element', async ({ page }) => {
        await page.goto(PRESENTATION_URL);

        // Seed order (by tt_content.sorting ASC):
        //   nth(0) -> uid 115 (cover slide, transition=convex)
        //   nth(1) -> uid 112 (text, transition=slide)
        //   nth(2) -> uid 117 (textmedia, transition=slide)
        //   nth(3) -> uid 105 (text, transition=slide)
        //   nth(4) -> uid 116 (code slide)
        const sections = page.locator('.reveal .slides > section');
        await expect(sections.nth(0)).toHaveAttribute('data-transition', 'convex');
        await expect(sections.nth(1)).toHaveAttribute('data-transition', 'slide');
        await expect(sections.nth(2)).toHaveAttribute('data-transition', 'slide');
    });

    test('cover slide template renders header inside .reveal-cover', async ({ page }) => {
        await page.goto(PRESENTATION_URL);

        const cover = page.locator('.reveal .slides .reveal-cover').first();
        // Wait for reveal.js to make the slide visible. The cover slide in
        // the seed is the third one, so it isn't .present initially — but
        // its DOM should still exist regardless of active state.
        await expect(cover).toHaveCount(1);
    });

    test('textmedia slide template renders inside .reveal-textmedia with imageorient modifiers', async ({ page }) => {
        await page.goto(PRESENTATION_URL);

        const textmedia = page.locator('.reveal .slides .reveal-textmedia');
        await expect(textmedia).toHaveCount(1);
        await expect(textmedia.locator('h2')).toContainText('Media Slide');

        // Seed has imageorient=26 ("Beside Text: Left"), which the
        // GalleryProcessor translates into horizontal='left',
        // vertical='intext', noWrap=true. The template must surface all
        // three on the wrapper so Layouts.css can target the layout.
        await expect(textmedia).toHaveClass(/reveal-textmedia-left/);
        await expect(textmedia).toHaveClass(/reveal-textmedia-intext/);
        await expect(textmedia).toHaveClass(/reveal-textmedia-nowrap/);
    });

    test('reveal.js asset chain is present in the rendered HTML', async ({ page }) => {
        const response = await page.goto(PRESENTATION_URL);
        const html = await response!.text();

        expect(html).toContain('Vendor/revealjs/dist/reveal.css');
        expect(html).toContain('Vendor/revealjs/dist/reveal.mjs');
        expect(html).toContain('data-revealjs-options=');
    });

    test('code slide is rendered by Monaco (read-only) for anonymous viewers', async ({ page }) => {
        // Architecture under test: the server emits plain
        // `<pre data-monaco data-language="X" data-editable="0">…raw…</pre>`
        // blocks; the page-wide RevealMonacoMount.js module loaded by
        // RevealJsMonacoAssetsProcessor walks them on DOMContentLoaded and
        // mounts a Monaco editor (read-only mode for anonymous viewers)
        // inside each. The test has TWO halves:
        //   (a) the raw HTML response carries the unhydrated `<pre data-monaco>`
        //       markup AND the RevealMonacoMount.js module reference, but
        //       no codemirror/@typo3-backend pollution.
        //   (b) after page load, each block has been promoted to a
        //       `.monaco-editor` instance with `data-monaco-mounted="1"`
        //       on the wrapper.

        // ----- (a) raw response -----
        const response = await page.goto(PRESENTATION_URL);
        const html = await response!.text();

        // Server emits `<pre data-monaco>`; seeded code slide (tt_content
        // uid 116) has 2 inline-IRRE children in
        // tx_revealjseditor_codeblock.csv (uids 11, 12 → parent 116).
        const rawBlocks = html.match(/<pre data-monaco/g) ?? [];
        expect(rawBlocks.length).toBeGreaterThanOrEqual(2);

        // RevealMonacoMount.js is loaded for the page-wide mount pass.
        expect(html).toContain('RevealMonacoMount.js');

        // No CodeMirror or Shiki/shikicode markup — Monaco is the single
        // editor across all surfaces now.
        expect(html).not.toContain('<typo3-t3editor-codemirror ');
        expect(html).not.toContain('@codemirror/');
        expect(html).not.toContain('@typo3/backend/code-editor');
        expect(html).not.toMatch(/<reveal-editable-(shiki|codemirror|phiki) /);
        expect(html).not.toContain('<pre class="shiki');
        expect(html).not.toContain('ShikiHighlight.js');

        // Each editable wrapper carries data-editable="0" on the public
        // deck (anonymous viewers don't get the dataHandlerStore bridge).
        expect(html).toMatch(/<pre data-monaco[^>]*data-editable="0"/);

        // ----- (b) hydrated DOM -----
        // Monaco mounts asynchronously after the vendored editor.mjs bundle
        // (~1 MB gzipped) loads + the core editor worker spins up. Wait
        // for the network to settle before asserting on the mounted DOM.
        await page.waitForLoadState('networkidle');
        const blocks = page.locator('.reveal pre[data-monaco][data-monaco-mounted="1"]');
        await expect(blocks.first()).toBeAttached({ timeout: 20_000 });

        // Each block contains a `.monaco-editor` div after mount — Monaco's
        // root class name on the editor instance it builds.
        await expect(
            page.locator('.reveal pre[data-monaco] .monaco-editor').first(),
        ).toBeAttached({ timeout: 20_000 });

        const hydratedCount = await blocks.count();
        expect(hydratedCount).toBeGreaterThanOrEqual(2);
    });

    test('keyboard ArrowRight advances reveal.js to the next slide', async ({ page }) => {
        await page.goto(PRESENTATION_URL);
        await expect(page.locator('body.reveal-viewport')).toBeVisible();

        const sections = page.locator('.reveal .slides > section');

        // Initially reveal.js marks slide 0 as .present.
        await expect(sections.nth(0)).toHaveClass(/\bpresent\b/);

        // Focus the deck so keyboard events reach reveal.js, then advance.
        await page.locator('.reveal').click();
        await page.keyboard.press('ArrowRight');

        // After advancing, slide 1 is .present and slide 0 falls back to .past.
        await expect(sections.nth(1)).toHaveClass(/\bpresent\b/);
        await expect(sections.nth(0)).toHaveClass(/\bpast\b/);
    });
});
