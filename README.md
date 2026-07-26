# revealjs_editor

Create [reveal.js](https://revealjs.com/) presentations as TYPO3 pages. One
TYPO3 page (doktype **1731**) becomes one reveal.js deck, edited entirely in the
TYPO3 backend — with a Monaco (VS Code) editor for code slides, a rich-text
editor for prose and speaker notes, and the
[visual_editor](https://extensions.typo3.org/extension/visual_editor) preview
for click-to-edit slides.

- Composer package: `ochorocho/revealjs-editor`
- Requires: TYPO3 `^13 || ^14`, PHP `^8.2`
- License: GPL-2.0-or-later

## Features

- **One page = one deck.** A custom page type (**Reveal Presentation**, doktype
  1731) turns a page and its content elements into a reveal.js slide deck.
- **47 deck options** (transitions, controls, auto-advance, navigation, scroll
  view, …) as page fields, plus **~26 per-slide options** (per-slide transition,
  background image/video/color/iframe, auto-animate, visibility, …).
- **Four slide content types**: `text` and `textmedia` (TYPO3 core), a **cover**
  slide (`revealjs_slide_cover`), and a **code** slide (`revealjs_slide_code`)
  with one Monaco editor per code block.
- **Speaker notes** are a rich-text field per slide; press `S` on a deck to open
  the reveal.js speaker view.
- **Two bundled themes** — `the-caemp` (default) and `typo3` — plus a per-site
  color and logo configuration that any theme inherits (see *Configuration*).

## Installation

```bash
composer require ochorocho/revealjs-editor
```

Run the extension setup and flush caches so the schema analyser creates the
`tx_revealjseditor_*` columns and the site set becomes available:

```bash
vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush
```

Add the **RevealjsEditor** site set (`ochorocho/revealjs-editor`) to your site
configuration, then create a page of type **Reveal Presentation** (doktype 1731)
and add slides to it.

## Usage

Each slide is a content element on the deck page. On a Reveal Presentation page
the new-content wizard is restricted to the four supported types:

- **Cover** (`revealjs_slide_cover`) — title / subtitle / rich-text body / image.
- **Code** (`revealjs_slide_code`) — a title plus any number of code blocks,
  each with its own language and Monaco editor; prose around them is rich text.
- **Text** / **Textmedia** — the TYPO3 core elements.

Every slide also gets **Slide**, **Background** and **Auto-animate** option tabs
(per-slide transition, background media/color, speaker notes, etc.). Speaker
notes are rich text and surface only in the speaker view (`S`), never on the
deck itself.

## Themes

Two themes ship and are selectable in the page's **Theme** field:

- **`the-caemp`** (default) — dark, brand yellow.
- **`typo3`** — anthracite background, TYPO3 Key Orange accents, Source Sans 3.

Each theme defines its own default palette and default logo; the per-site
settings below override them when set (see *Configuration*).

Other extensions (e.g. a sitepackage) can register additional themes without
patching this extension — from their `ext_localconf.php`:

```php
// theme value -> CSS file
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['revealjs_editor']['themes']['my-theme']
    = 'EXT:my_sitepackage/Resources/Public/Css/my-theme.css';

// optional: a default logo for that theme
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['revealjs_editor']['themeLogos']['my-theme']
    = 'EXT:my_sitepackage/Resources/Public/Images/my-logo.svg';
```

The registered value becomes selectable in the *Theme* field and is resolved at
render time.

## Configuration & inheritance

Colours and the logo are layered: a **theme default** at the bottom, an optional
**site-wide setting** in the middle, and an optional **per-page** value on top.
The most specific value that is set wins.

### Site settings

Defined by the site set and editable per site — in the **Site Settings** backend
module, or under `settings:` in `config/sites/<site>/config.yaml` (or a sibling
`config/sites/<site>/settings.yaml`):

| Setting | Default | Effect |
| --- | --- | --- |
| `revealjs.defaultLogo` | *(empty)* | A path / `EXT:` URL that overrides the theme's default logo when set. Empty = use the theme's own logo. |
| `revealjs.color.background` | *(empty)* | Deck background. Empty = theme default. |
| `revealjs.color.text` | *(empty)* | Body text (and selection foreground). |
| `revealjs.color.accent` | *(empty)* | Headings, links, markers, borders, progress bar, controls, cover/author wordmark — and every tint derived from it. |
| `revealjs.color.muted` | *(empty)* | Subdued divider rules. |

The colour settings are hex values (e.g. `#ff8700`). They are left **empty by
default on purpose**: an empty value means "use the active theme's own colour",
so each theme looks right out of the box, and filling one in recolours **any**
theme consistently (the whole palette derives from these four tokens).

Example — recolour every deck on a site to a custom accent while keeping the
theme's other colours:

```yaml
# config/sites/<site>/settings.yaml
revealjs:
  color:
    accent: '#0080c9'
```

### Logo resolution (highest → lowest)

1. **Per-page logo** — the page field *Logo* (`tx_revealjseditor_logo`, a FAL
   image). Set it to give a single presentation its own logo.
2. **`revealjs.defaultLogo`** site setting — used only when non-empty.
3. **Theme default logo** — `the-caemp` → its logo, `typo3` → the TYPO3 mark.

### Colour resolution (highest → lowest)

1. **`revealjs.color.*`** site setting — used only when non-empty.
2. **Theme default** — the palette declared by the active theme.

There is also a per-page **Author** field (`tx_revealjseditor_author`), shown
lower-left on every slide when set.

## Deployment

reveal.js and Monaco are shipped as native ES modules (`.mjs`). **Your web
server must serve `.mjs` with a JavaScript MIME type** (`text/javascript`).
nginx's default `mime.types` does not map `.mjs`, so it falls back to
`application/octet-stream` and browsers refuse to execute the modules — the deck
loads but stays blank and the console shows a module MIME-type error.

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

This is a server-configuration concern, not an extension setting; DDEV's bundled
nginx already serves `.mjs` correctly.

### Speaker notes / preview (cHash)

The reveal.js speaker view (`S`) and the visual_editor backend preview append
query parameters to page URLs. The extension registers those as `cHash`-excluded
(`FE.cacheHash.excludedParameters`, set in `ext_localconf.php`), so they work out
of the box even with TYPO3 v14's strict `cacheHash.enforceValidation` — no site
configuration required.
