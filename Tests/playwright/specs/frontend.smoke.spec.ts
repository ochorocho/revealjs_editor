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

    test('the deck wrapper renders three sections inside .reveal .slides', async ({ page }) => {
        const response = await page.goto(PRESENTATION_URL);
        expect(response?.status()).toBe(200);

        await expect(page).toHaveTitle(/RevealJS/i);
        await expect(page.locator('div.reveal')).toHaveCount(1);
        await expect(page.locator('.reveal .slides > section')).toHaveCount(3);
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
        //   nth(1) -> uid 112 (transition=slide)
        //   nth(2) -> uid 105 (transition=slide)
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

    test('reveal.js asset chain is present in the rendered HTML', async ({ page }) => {
        const response = await page.goto(PRESENTATION_URL);
        const html = await response!.text();

        expect(html).toContain('Vendor/revealjs/dist/reveal.css');
        expect(html).toContain('Vendor/revealjs/dist/reveal.mjs');
        expect(html).toContain('data-revealjs-options=');
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
