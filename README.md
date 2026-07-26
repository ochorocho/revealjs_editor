# revealjs_editor

Create [reveal.js](https://revealjs.com/) presentations as TYPO3 pages. One
TYPO3 page (doktype **1731**) becomes one reveal.js deck, edited entirely in
the TYPO3 backend with a Monaco (VS Code) editor for code slides and the
[visual_editor](https://extensions.typo3.org/extension/visual_editor) preview
for click-to-edit slides.

- Composer package: `ochorocho/revealjs-editor`
- Requires: TYPO3 `^13 || ^14`, PHP `^8.2`
- License: GPL-2.0-or-later

## Installation

```bash
composer require ochorocho/revealjs-editor
```

Then, in the backend, run the extension setup / flush caches so the schema
analyser creates the `tx_revealjseditor_*` columns and the site set is
available:

```bash
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush
```

Add the **RevealjsEditor** site set (`ochorocho/revealjs-editor`) to your site
configuration, then create a page of type **Reveal Presentation** (doktype
1731) and add slides to it.

## Deployment

reveal.js and Monaco are shipped as native ES modules (`.mjs`). **Your web
server must serve `.mjs` with a JavaScript MIME type** (`text/javascript`).
nginx's default `mime.types` does not map `.mjs`, so it falls back to
`application/octet-stream` and browsers refuse to execute the modules — the
deck loads but stays blank and the console shows a module MIME-type error.

**nginx** — add the mapping (e.g. in your server/http block or a snippet):

```nginx
types {
    text/javascript js mjs;
}
```

**Apache** — `.htaccess` or vhost:

```apache
AddType text/javascript .mjs
```

This is a server-configuration concern, not an extension setting; DDEV's
bundled nginx already serves `.mjs` correctly.

## Themes

The bundled **THE CÄMP** theme (`the-caemp`) is the default and only shipped
theme. Other extensions (e.g. a sitepackage) can register their own themes
without patching this extension — from their `ext_localconf.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['revealjs_editor']['themes']['my-theme']
    = 'EXT:my_sitepackage/Resources/Public/Css/my-theme.css';
```

The registered value becomes selectable in the page's *Theme* field and is
resolved to its CSS at render time.
