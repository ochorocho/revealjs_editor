#!/usr/bin/env bash
#
# Idempotent bootstrap for the TYPO3 dev installation under Build/.
# Run automatically as a DDEV post-start hook. Safe to re-run at any time.
#
# Steps (each guarded by a marker so repeated runs are no-ops):
#   1. composer install inside Build/
#   2. typo3 setup against the dev database `db`   (browseable at revealjs-editor.ddev.site)
#   3. typo3 setup against the test database `test` (used by Playwright; created if missing)
#   4. Overlay site fixtures from Tests/playwright/fixtures/sites/
#   5. Write Build/config/system/additional.php with the X-Test-Run header DB switch
#
# Inside the DDEV web container:
#   /var/www/html/           -> project root (the extension)
#   /var/www/html/Build/     -> full TYPO3 installation
#   db service:              host=db user=db pass=db, DBs: db (dev) and test (Playwright)

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
        "b13/container": "^3.1",
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

# Helper: run typo3 setup against a given database (creates it if missing).
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
    else
        echo "[revealjs-editor] Database '${db_name}' already populated, skipping typo3 setup."
    fi
}

# 2a. Recovery: if settings.php is gone (e.g. an interrupted ddev restart wiped
#     `config/system/` but left the databases populated) `run_typo3_setup` would
#     short-circuit on the table-count check and never recreate the install.
#     Drop any tables in either database so the next setup call goes through.
if [ ! -f "${SETTINGS_FILE}" ]; then
    for db_name in db test; do
        table_count=$(mysql -h db -u db -pdb "${db_name}" -sN -e \
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${db_name}';" \
            2>/dev/null || echo "0")
        if [ "${table_count}" -ne 0 ]; then
            echo "[revealjs-editor] settings.php missing but '${db_name}' has ${table_count} tables — wiping for fresh setup ..."
            mysql -h db -u root -proot -e "DROP DATABASE IF EXISTS \`${db_name}\`; CREATE DATABASE \`${db_name}\`;" >/dev/null
        fi
    done
fi

# 2. dev database: settings.php is created here on first run
if [ ! -f "${SETTINGS_FILE}" ]; then
    run_typo3_setup "db" "revealjs-editor"
else
    echo "[revealjs-editor] settings.php already exists; ensuring 'db' tables are populated ..."
    run_typo3_setup "db" "revealjs-editor"
fi

# 3. test database: fresh schema for Playwright runs
run_typo3_setup "test" "revealjs-editor (test)"

# 3b. Always seed the `test` database with the Playwright fixtures so it has
#     the same content that's visible during automated runs.
#     This makes interactive Playwright sessions usable out of the box:
#         ddev playwright --dir=Tests/playwright browser --project=backend
#     The same file is also applied per-worker by `dbConfig.seedFiles` in
#     playwright.config.ts; seed.sql leads with TRUNCATE statements, so
#     re-importing it from either side is idempotent.
SEED_FILE="/var/www/html/Tests/playwright/fixtures/seed.sql"
if [ -f "${SEED_FILE}" ]; then
    echo "[revealjs-editor] Seeding 'test' database from $(basename "${SEED_FILE}") ..."
    mysql -h db -u db -pdb test < "${SEED_FILE}"
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

# 5. Write additional.php with the Playwright DB switch (idempotent overwrite).
#    `mkdir -p` is belt-and-braces against any state where typo3 setup ran but
#    the parent directory was reset between then and now.
echo "[revealjs-editor] Writing additional.php with X-Test-Run DB switch ..."
mkdir -p "$(dirname "${ADDITIONAL_FILE}")"
cat > "${ADDITIONAL_FILE}" <<'PHP'
<?php
// Allow the DDEV hostname (and any test/playwright sub-hostname).
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';

// Switch the default DB connection to "test" when Playwright requests it
// via the X-Test-Run: 1 header. Devs hitting the site directly without
// the header keep reading from the dev `db` database.
if (($_SERVER['HTTP_X_TEST_RUN'] ?? '') === '1') {
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = 'test';
}
PHP

echo "[revealjs-editor] TYPO3 is ready."
echo "[revealjs-editor] Backend (dev DB):   ${SITE_BASE_URL}typo3"
echo "[revealjs-editor] Login:              ${ADMIN_USER} / ${ADMIN_PASS}"
echo "[revealjs-editor] Playwright DB:      'test' (active when X-Test-Run header is set)"
