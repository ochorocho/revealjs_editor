#!/usr/bin/env bash
#
# Idempotent bootstrap for the TYPO3 dev installation under Build/.
# Run automatically as a DDEV post-start hook. Safe to re-run at any time.
#
# Steps (each guarded by a marker so repeated runs are no-ops):
#   1. composer install inside Build/
#   2. typo3 setup against the dev database `db`   (browseable at revealjs-editor.ddev.site)
#   3. Import per-table CSV seed fixtures into `db` (only when typo3 setup
#      ran fresh schema this round — so editor's WIP state is preserved
#      on subsequent restarts). Done via Tests/playwright/scripts/import-seeds.mjs
#      using the @ochorocho/playwright-db-connector npm package.
#   4. Overlay site fixtures from Tests/playwright/fixtures/sites/
#   5. Write a minimal Build/config/system/additional.php (trustedHostsPattern only).
#
# Inside the DDEV web container:
#   /var/www/html/           -> project root (the extension)
#   /var/www/html/Build/     -> full TYPO3 installation
#   db service:              host=db user=db pass=db, single DB: db

set -euo pipefail

BUILD_DIR="/var/www/html/Build"
SETTINGS_FILE="${BUILD_DIR}/config/system/settings.php"
ADDITIONAL_FILE="${BUILD_DIR}/config/system/additional.php"
SITES_FIXTURE_DIR="/var/www/html/Tests/playwright/fixtures/sites"
SITE_BASE_URL="https://revealjs-editor.ddev.site/"
ADMIN_EMAIL="typo3@b13.com"
ADMIN_USER="admin"
ADMIN_PASS='Password.1'

cd "${BUILD_DIR}"

# 0. Generate Build/composer.json from a heredoc, parameterised by
#    ${TYPO3_VERSION} (default `^14.3`). The file is no longer committed —
#    the script is the single source of truth. The marker file under
#    Build/var/typo3-version caches the version we last wrote so repeated
#    `ddev restart`s with the same TYPO3_VERSION are no-ops.
#
#    On a real version *switch* (marker present and differs), the existing
#    install state — composer artefacts, settings.php, caches, generated
#    public/ entries, the dev/test databases — is wiped so the new major
#    version comes up on a virgin schema. `public/fileadmin` and
#    `public/typo3temp` are DDEV-managed volumes and can't be removed
#    wholesale, so only regeneratable paths under public/ are cleared.
TYPO3_VERSION="${TYPO3_VERSION:-^14.3}"
TYPO3_VERSION_MARKER="${BUILD_DIR}/var/typo3-version"
COMPOSER_JSON="${BUILD_DIR}/composer.json"

mkdir -p "$(dirname "${TYPO3_VERSION_MARKER}")"
CURRENT_TYPO3_VERSION=""
[ -f "${TYPO3_VERSION_MARKER}" ] && CURRENT_TYPO3_VERSION=$(cat "${TYPO3_VERSION_MARKER}")

if [ ! -f "${COMPOSER_JSON}" ] || [ "${TYPO3_VERSION}" != "${CURRENT_TYPO3_VERSION}" ]; then
    if [ -n "${CURRENT_TYPO3_VERSION}" ] && [ "${TYPO3_VERSION}" != "${CURRENT_TYPO3_VERSION}" ]; then
        echo "[revealjs-editor] Switching TYPO3 from ${CURRENT_TYPO3_VERSION} to ${TYPO3_VERSION} ..."
        rm -rf vendor composer.lock config/system var/cache var/log
        rm -rf public/typo3 public/_assets public/index.php 2>/dev/null || true
        # The DDEV `db` user can't DROP DATABASE; use root for the wipe.
        # Both `db` (dev) and `test` (Playwright) are dropped so they come up
        # on a virgin schema matching the new TYPO3 version.
        mysql -h db -u root -proot -e "
            DROP DATABASE IF EXISTS \`db\`;
            CREATE DATABASE \`db\`;
            DROP DATABASE IF EXISTS \`test\`;
            CREATE DATABASE \`test\`;
        " >/dev/null
    else
        echo "[revealjs-editor] Generating Build/composer.json (TYPO3 ${TYPO3_VERSION}) ..."
    fi

    # Unquoted EOF so ${TYPO3_VERSION} interpolates. JSON has no other `$`
    # tokens, so accidental shell expansion is not a concern.
    cat > "${COMPOSER_JSON}" <<EOF
{
    "name": "ochorocho/revealjs-editor-dev",
    "description": "Development TYPO3 installation for working on ochorocho/revealjs-editor.",
    "type": "project",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": "^8.3",
        "friendsoftypo3/visual-editor": "^1.4",
        "ochorocho/revealjs-editor": "@dev",
        "typo3/cms-backend": "${TYPO3_VERSION}",
        "typo3/cms-fluid-styled-content": "${TYPO3_VERSION}",
        "typo3/cms-core": "${TYPO3_VERSION}",
        "typo3/cms-extbase": "${TYPO3_VERSION}",
        "typo3/cms-extensionmanager": "${TYPO3_VERSION}",
        "typo3/cms-filelist": "${TYPO3_VERSION}",
        "typo3/cms-fluid": "${TYPO3_VERSION}",
        "typo3/cms-frontend": "${TYPO3_VERSION}",
        "typo3/cms-info": "${TYPO3_VERSION}",
        "typo3/cms-install": "${TYPO3_VERSION}",
        "typo3/cms-seo": "${TYPO3_VERSION}",
        "typo3/cms-setup": "${TYPO3_VERSION}",
        "typo3/cms-lowlevel": "${TYPO3_VERSION}",
        "typo3/cms-tstemplate": "${TYPO3_VERSION}"
    },
    "require-dev": {
        "friendsofphp/php-cs-fixer": "^3.64",
        "phpstan/phpstan": "^1.12",
        "typo3/coding-standards": "^0.8",
        "typo3/testing-framework": "^9.0 || ^10.0"
    },
    "repositories": [
        { "type": "path", "url": "../", "options": { "symlink": true } }
    ],
    "config": {
        "allow-plugins": {
            "typo3/cms-composer-installers": true,
            "typo3/class-alias-loader": true
        },
        "sort-packages": true,
        "vendor-dir": "vendor"
    },
    "extra": { "typo3/cms": { "web-dir": "public" } }
}
EOF

    echo "${TYPO3_VERSION}" > "${TYPO3_VERSION_MARKER}"
fi

# 1. Resolve composer dependencies in Build/. Uses `composer update` so the lock
#    stays in sync with composer.json — Build/composer.lock is git-ignored.
if [ ! -f "vendor/autoload.php" ] || [ ! -x "vendor/bin/typo3" ] || [ ! -x "vendor/bin/phpstan" ]; then
    echo "[revealjs-editor] Running composer update in Build/ ..."
    composer update --no-interaction --no-progress
else
    echo "[revealjs-editor] composer dependencies already installed, skipping."
fi

# Per-database flags tracking whether typo3 setup actually ran a fresh schema
# build this round. Set by run_typo3_setup() to 1 only when it had to populate
# a previously-empty schema; the step-3 seed loop checks them to decide which
# DBs get their initial CSV import. A plain `ddev restart` against existing
# schemas leaves both at 0 → no re-seed → editor's WIP and any in-progress
# test state both survive.
DB_SETUP_RAN_db=0
DB_SETUP_RAN_test=0

# Helper: run typo3 setup against a given database (creates it if missing).
# Sets DB_SETUP_RAN_<name>=1 in the parent scope when it had to populate
# the schema. The caller decides what to do with the flag.
run_typo3_setup() {
    local db_name="$1"
    local project_label="$2"

    # Note: DDEV pre-grants PUBLIC privileges on `test.*` and `test_%.*` to the
    # `db` user, so a bare CREATE DATABASE is enough — no GRANT required.
    mysql -h db -u db -pdb -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\`;"

    local table_count
    table_count=$(mysql -h db -u db -pdb "${db_name}" -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${db_name}';" 2>/dev/null || echo "0")

    if [ "${table_count}" -eq 0 ]; then
        echo "[revealjs-editor] Running 'typo3 setup' against database '${db_name}' ..."
        TYPO3_DB_DBNAME="${db_name}" vendor/bin/typo3 setup \
            --driver=mysqli \
            --host=db \
            --port=3306 \
            --dbname="${db_name}" \
            --username=db \
            --password=db \
            --admin-username="${ADMIN_USER}" \
            --admin-user-password="${ADMIN_PASS}" \
            --admin-email="${ADMIN_EMAIL}" \
            --project-name="${project_label}" \
            --server-type=other \
            --create-site="${SITE_BASE_URL}" \
            --force \
            --no-interaction
        # Set the per-DB flag in the parent scope so the seed loop below knows
        # which DB(s) just had their schema laid down.
        eval "DB_SETUP_RAN_${db_name}=1"
    else
        echo "[revealjs-editor] Database '${db_name}' already populated, skipping typo3 setup."
    fi
}

# 2a. Recovery: if settings.php is gone (e.g. an interrupted ddev restart wiped
#     `config/system/` but left the database populated) `run_typo3_setup` would
#     short-circuit on the table-count check and never recreate the install.
#     Drop the `db` schema so the next setup call goes through. (The `test`
#     schema can be left alone — settings.php is `db`'s settings.)
if [ ! -f "${SETTINGS_FILE}" ]; then
    table_count=$(mysql -h db -u db -pdb db -sN -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'db';" \
        2>/dev/null || echo "0")
    if [ "${table_count}" -ne 0 ]; then
        echo "[revealjs-editor] settings.php missing but 'db' has ${table_count} tables — wiping for fresh setup ..."
        mysql -h db -u root -proot -e "DROP DATABASE IF EXISTS \`db\`; CREATE DATABASE \`db\`;" >/dev/null
    fi
fi

# 2. Provision both databases. additional.php (written in step 5 below)
#    swaps the active connection to `test` per-request when the X-Test-Run
#    header is present, so Playwright reads/writes test while the editor's
#    browser keeps reading db.
#
#    Each `typo3 setup` call writes settings.php with --dbname pointing at
#    whichever DB it just initialised. When both DBs already exist (an
#    incremental restart), the helper short-circuits and settings.php is
#    untouched. When only one DB is fresh, settings.php would end up
#    pointing at THAT db — even if it's `test`. We unconditionally restore
#    `dbname` to `db` immediately after the setup pair below so the
#    editor's default is always `db` regardless of which one needed to be
#    rebuilt.
run_typo3_setup "db"   "revealjs-editor"
run_typo3_setup "test" "revealjs-editor (test)"

# Force settings.php's Default connection to point at `db` (the editor's
# primary), regardless of which run_typo3_setup call wrote it last. This
# is the line `'dbname' => '...',` under DB.Connections.Default.
# additional.php (step 5) will route to `test` per-request when the
# X-Test-Run header is set.
if [ -f "${SETTINGS_FILE}" ]; then
    sed -i.bak -E "s/^([[:space:]]*'dbname' => )'[^']*',/\\1'db',/" "${SETTINGS_FILE}"
    rm -f "${SETTINGS_FILE}.bak"
fi

# 3. Import the per-table CSV seed fixtures into whichever DB(s) just had
#    their schema laid down by step 2. Skipping when the schema was already
#    populated preserves any WIP edits the developer made between restarts;
#    on a fresh start both DBs get seeded with the same demo content.
#
#    The CSVs live under Tests/playwright/fixtures/csv/{be_users,be_groups,
#    pages,tt_content}.csv and are loaded via Tests/playwright/scripts/
#    import-seeds.mjs (uses @ochorocho/playwright-db-connector's
#    CsvLoader.importFile). The Playwright globalSetup also seeds `test`
#    on every `ddev playwright test` run, so this bootstrap-time import is
#    really only the safety net for "I ran ddev restart but haven't run
#    Playwright yet" — it makes the test DB browsable immediately.
SEED_SCRIPT="/var/www/html/Tests/playwright/scripts/import-seeds.mjs"
if [ -f "${SEED_SCRIPT}" ] \
   && [ -d "/var/www/html/node_modules/@ochorocho/playwright-db-connector" ]; then
    seeded_any=0
    for db_name in db test; do
        ran_var="DB_SETUP_RAN_${db_name}"
        if [ "${!ran_var}" -eq 1 ]; then
            echo "[revealjs-editor] Importing CSV seed fixtures into '${db_name}' ..."
            TYPO3_DB_NAME="${db_name}" node "${SEED_SCRIPT}"
            seeded_any=1
        fi
    done
    # The CSV import bypasses TYPO3's DataHandler so it leaves any DB-level
    # page caches (cache_pages, cache_pages_tags, ...) pointing at whatever
    # was rendered before the import. Force a full TYPO3 cache flush so the
    # next FE request re-renders with the freshly imported content.
    if [ "${seeded_any}" -eq 1 ] && [ -x vendor/bin/typo3 ]; then
        echo "[revealjs-editor] Flushing TYPO3 caches after seed import ..."
        vendor/bin/typo3 cache:flush --no-interaction || true
    fi
elif [ "${DB_SETUP_RAN_db}" -eq 1 ] || [ "${DB_SETUP_RAN_test}" -eq 1 ]; then
    echo "[revealjs-editor] node_modules missing — skipping CSV seed import."
    echo "[revealjs-editor] Run 'ddev npm install' followed by"
    echo "[revealjs-editor]   ddev exec node ${SEED_SCRIPT}                  # seeds db"
    echo "[revealjs-editor]   ddev exec sh -c 'TYPO3_DB_NAME=test node ${SEED_SCRIPT}'  # seeds test"
    echo "[revealjs-editor] or just run 'ddev playwright test' which seeds test via globalSetup."
fi

# 4a. Flush TYPO3 caches so a stale DI container can't serve outdated
#     constructor signatures after code changes (notably HrefLangService).
if [ -x "vendor/bin/typo3" ]; then
    rm -rf "${BUILD_DIR}/var/cache/" || true
fi

# 4. Overlay site configs from fixtures (idempotent).
#    Also removes the auto-created `main` site — its rootPageId=1 conflicts
#    with the fixture's `germany` site once fixture pages are loaded.
if [ -d "${SITES_FIXTURE_DIR}" ]; then
    echo "[revealjs-editor] Overlaying site configs from fixtures ..."
    mkdir -p "${BUILD_DIR}/config/sites"
    rm -rf "${BUILD_DIR}/config/sites/main"
    for site in "${SITES_FIXTURE_DIR}"/*/; do
        site_name=$(basename "${site}")
        rm -rf "${BUILD_DIR}/config/sites/${site_name}"
        cp -R "${site}" "${BUILD_DIR}/config/sites/${site_name}"
    done
fi

# 5. Write a minimal additional.php (idempotent overwrite). Two duties:
#    (a) widen trustedHostsPattern so DDEV's *.ddev.site sub-hostnames reach
#        TYPO3 v14's stricter default; and
#    (b) swap the default DB connection to `test` when the request carries
#        the `X-Test-Run: 1` header. Playwright sets that header globally
#        via `extraHTTPHeaders` in playwright.config.ts, so every Playwright
#        request (page navigation, XHR, the `request` API) routes to `test`.
#        The editor's browser doesn't send the header → keeps reading `db`.
#        The override happens at request bootstrap, BEFORE any DB connection
#        opens, which is exactly where dbname overrides belong.
echo "[revealjs-editor] Writing additional.php ..."
mkdir -p "$(dirname "${ADDITIONAL_FILE}")"
cat > "${ADDITIONAL_FILE}" <<'PHP'
<?php
// Allow the DDEV hostname (and any sub-hostname).
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';

// Header-based DB switch for Playwright. The X-Test-Run header is set on
// every Playwright request (playwright.config.ts → use.extraHTTPHeaders);
// the editor's browser doesn't send it, so the editor stays on `db`.
// Don't deploy this file to production — it trusts an arbitrary HTTP header.
if (($_SERVER['HTTP_X_TEST_RUN'] ?? '') === '1') {
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'test';
}
PHP

echo "[revealjs-editor] TYPO3 is ready."
echo "[revealjs-editor] Backend:            ${SITE_BASE_URL}typo3"
echo "[revealjs-editor] Login:              ${ADMIN_USER} / ${ADMIN_PASS}"
echo "[revealjs-editor] Editor → 'db'   |   Playwright (X-Test-Run header) → 'test'"
echo "[revealjs-editor] CSV fixtures: Tests/playwright/fixtures/csv/"
