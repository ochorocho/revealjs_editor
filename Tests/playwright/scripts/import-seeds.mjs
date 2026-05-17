#!/usr/bin/env node
// Bootstrap seed importer for the revealjs_editor extension.
//
// Imports the per-table CSV fixtures under Tests/playwright/fixtures/csv/
// into the dev database (`db`). Called from .ddev/scripts/setup-typo3.sh
// once `typo3 setup` has created the schema, so the dev install starts
// with the same demo content the Playwright suite expects to find.
//
// Connection details come from env vars (TYPO3_DB_HOST, TYPO3_DB_PORT,
// TYPO3_DB_USER, TYPO3_DB_PASSWORD, TYPO3_DB_NAME). Defaults assume
// invocation from inside the DDEV web container where the `db` service
// is reachable as the hostname `db`.
//
// The import order respects FK dependencies:
//   be_groups → be_users → pages → tt_content
//
// Each table is TRUNCATEd before its CSV is loaded, so the script is
// idempotent (re-running it produces the same end state regardless of
// whatever state the DB happened to be in beforehand).

import { CsvLoader } from '@ochorocho/playwright-db-connector';
import knex from 'knex';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const fixturesDir = resolve(here, '..', 'fixtures', 'csv');

// FK-dependency order. Children depend on parents being present:
//   - be_users reference be_groups via `usergroup`
//   - tt_content rows reference pages.uid via `pid`
//   - tx_revealjseditor_codeblock rows reference tt_content.uid via `parent_uid`
const ORDER = ['be_groups', 'be_users', 'pages', 'tt_content', 'tx_revealjseditor_codeblock'];

// TYPO3 v14 page-cache tables. Bypassing DataHandler to insert fixtures
// means the cache otherwise keeps serving HTML rendered against whatever
// was in the tables before the import. Truncating these makes the script
// self-contained: the next FE/BE request re-renders cleanly.
const CACHE_TABLES = [
    'cache_pages',
    'cache_pages_tags',
    'cache_hash',
    'cache_hash_tags',
    'cache_rootline',
    'cache_rootline_tags',
];

const config = {
    host: process.env.TYPO3_DB_HOST ?? 'db',
    port: Number(process.env.TYPO3_DB_PORT ?? 3306),
    user: process.env.TYPO3_DB_USER ?? 'db',
    password: process.env.TYPO3_DB_PASSWORD ?? 'db',
    database: process.env.TYPO3_DB_NAME ?? 'db',
};

console.log(
    `[import-seeds] Connecting to mysql://${config.user}@${config.host}:${config.port}/${config.database}`,
);

const db = knex({
    client: 'mysql2',
    connection: config,
    pool: { min: 0, max: 1 },
});

/**
 * `true` if a table with that name exists in the connected database.
 * Used to skip TRUNCATE + import for tables that haven't been created
 * yet (e.g. when `typo3 setup` failed earlier in the bootstrap) so
 * the seed step doesn't fatal-error before reaching the next table.
 * Queries information_schema so we don't depend on database-specific
 * error codes (ER_NO_SUCH_TABLE is MySQL/MariaDB; SHOW TABLES is also
 * MySQL-family, but used here for parity with the rest of this script).
 */
async function tableExists(name) {
    const rows = await db('information_schema.tables')
        .where({ table_schema: config.database, table_name: name })
        .select(db.raw('1'))
        .limit(1);
    return rows.length > 0;
}

try {
    // FK_CHECKS=0 lets us TRUNCATE tables that participate in foreign keys
    // in any order, and lets us re-seed without worrying about transient
    // referential-integrity violations between tables. Restored at the end.
    await db.raw('SET FOREIGN_KEY_CHECKS = 0');

    // The fixtures carry empty strings for some integer/decimal columns
    // (matches the original seed.sql, which relied on MySQL's lenient
    // auto-conversion '' → 0). Modern MariaDB defaults to STRICT_TRANS_TABLES
    // which rejects those, so we relax the per-session sql_mode for the
    // duration of the import. The dev database keeps its server-wide
    // sql_mode untouched.
    await db.raw("SET SESSION sql_mode = ''");

    for (const table of ORDER) {
        // Tolerate missing tables — e.g. when `typo3 setup` partially
        // failed (row-size limit, FK conflict, ...), the schema for
        // that table may not exist yet. Warning instead of fatal lets
        // the rest of the seed still land; the operator sees what was
        // skipped and can re-run setup to fix the underlying issue.
        if (!(await tableExists(table))) {
            console.warn(`[import-seeds] Skipping ${table}: table does not exist`);
            continue;
        }
        const csvPath = resolve(fixturesDir, `${table}.csv`);
        console.log(`[import-seeds] Truncating ${table} ...`);
        await db.raw(`TRUNCATE TABLE \`${table}\``);
        console.log(`[import-seeds] Importing ${csvPath} ...`);
        await CsvLoader.importFile(db, csvPath);
    }

    // Invalidate page caches that were populated against the pre-import
    // content. Skipped tables (e.g. on a fresh install where typo3 setup
    // hasn't created them yet) are tolerated via the same exists-check.
    console.log('[import-seeds] Truncating page cache tables ...');
    for (const table of CACHE_TABLES) {
        if (!(await tableExists(table))) continue;
        await db.raw(`TRUNCATE TABLE \`${table}\``);
    }

    await db.raw('SET FOREIGN_KEY_CHECKS = 1');
    console.log('[import-seeds] Done.');
} catch (err) {
    console.error('[import-seeds] Import failed:', err);
    process.exitCode = 1;
} finally {
    await db.destroy();
}
