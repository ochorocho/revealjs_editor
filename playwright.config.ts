import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for the revealjs_editor extension.
 *
 * Setup model:
 *   - Tests run **inside DDEV** via the `ochorocho/ddev-playwright` add-on
 *     (installed under `.ddev/docker-compose.playwright.yaml`). The add-on
 *     adds a sidecar `playwright` service running the official Microsoft
 *     Playwright Docker image (pinned to v1.59.0 in `.ddev/.env.playwright`,
 *     matching the host's `@playwright/test`).
 *
 *   - The Playwright container links to the `web` service, so tests reach
 *     the TYPO3 install at the internal docker-network hostname `http://web`
 *     — no HTTPS, no DDEV traefik in the path. Override via
 *     `PLAYWRIGHT_BASE_URL` to point at any other endpoint.
 *
 *   - **Single database.** Both the editor and Playwright hit the dev `db`
 *     database. The seed fixtures under `Tests/playwright/fixtures/csv/` are
 *     loaded into `db` once by `setup-typo3.sh` (when typo3 setup runs a
 *     fresh schema). After that the database carries the demo content
 *     editors see in the BE plus whatever they edit, and Playwright's
 *     read-only navigation tests can rely on the seeded uids/slugs.
 *
 *   - **DB fixture for write-tests.** The `dbConfig` block below wires the
 *     `db` Playwright fixture from `@ochorocho/playwright-db-connector`. Any
 *     spec that imports `test`/`expect` from that package gets a knex-backed
 *     connection and the `seeInDatabase()` / `haveInDatabase()` API. The
 *     default `cleanupStrategy: 'transaction'` rolls back any writes the
 *     fixture makes — so write-tests leave `db` untouched. (TYPO3 BE form
 *     saves still go through TYPO3's own connection and aren't wrapped, so
 *     don't write through the FormEngine in tests unless you mean to.)
 *
 * Run:
 *   npm install                          # one-off (installs deps)
 *   ddev add-on get ochorocho/ddev-playwright  # one-off
 *   ddev restart                         # bring up the playwright sidecar
 *                                        # + import CSV fixtures into `db`
 *   ddev playwright test                 # run all specs in the addon container
 *   ddev playwright browser              # interactive UI mode
 *   ddev playwright show-report          # last HTML report
 */
export default defineConfig({
    testDir: './Tests/playwright/specs',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: [['list'], ['html', { open: 'never' }]],
    timeout: 30_000,
    expect: { timeout: 10_000 },

    use: {
        // Inside the addon's Playwright container, the TYPO3 web service is
        // reachable at the docker-network hostname `web` over plain HTTP.
        // Override with PLAYWRIGHT_BASE_URL when running against a deployed
        // environment from outside DDEV.
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://web',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',

        // Knex-backed DB fixture. Specs that opt in by importing `test` from
        // `@ochorocho/playwright-db-connector` get a `db` argument they can
        // call `seeInDatabase()`, `haveInDatabase()`, etc. on; transaction
        // cleanup is the default. From inside the addon's Playwright
        // container, the DDEV `db` service is reachable at `db:3306`. The
        // value from `process.env.PLAYWRIGHT_DB_*` lets ad-hoc runs override.
        // TYPO3's primary key is `uid`, not `id` — the fixture needs that
        // hint for `haveInDatabase()` to return the right value.
        // @ts-expect-error — the type comes from the merged fixtures.
        dbConfig: {
            client: 'mysql2',
            connection: {
                host: process.env.PLAYWRIGHT_DB_HOST ?? 'db',
                port: Number(process.env.PLAYWRIGHT_DB_PORT ?? 3306),
                user: process.env.PLAYWRIGHT_DB_USER ?? 'db',
                password: process.env.PLAYWRIGHT_DB_PASSWORD ?? 'db',
                database: process.env.PLAYWRIGHT_DB_NAME ?? 'db',
            },
            primaryKeyColumn: 'uid',
            cleanupStrategy: 'transaction',
        },
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
