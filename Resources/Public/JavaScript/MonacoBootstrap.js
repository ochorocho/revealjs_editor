/**
 * Monaco bootstrap — single source of truth for "how does Monaco load".
 *
 * Both Monaco mount surfaces in this extension import from here:
 *   - RevealMonacoMount.js  → FE deck (anonymous viewers + visual_editor
 *                              preview iframe), one editor per
 *                              `<pre data-monaco>` block.
 *   - MonacoFormEngineElement.js → TYPO3 BE FormEngine, one editor per
 *                                   `<typo3-monaco-editor>` custom element.
 *
 * Centralising the three shared concerns here keeps both sites identical:
 *   1. `self.MonacoEnvironment.getWorkerUrl` — maps Monaco's worker labels
 *      (`json`, `css`, `html`, `typescript`, …) to the URLs of our vendored
 *      worker scripts under `Vendor/monaco/`. Monaco reads this at module
 *      init time; the bootstrap MUST run before `editor.mjs` is imported.
 *   2. `editor.css` `<link>` injection — Monaco's chrome (carets, scrollbars,
 *      completion popup, codicon icons) lives in a sibling stylesheet that
 *      needs to be in `<head>` before the first editor mounts.
 *   3. The dynamic `import('./Vendor/monaco/editor.mjs')` — exported as a
 *      ready-to-await Promise so callers share a single Monaco singleton
 *      instead of pulling the ~1 MB bundle twice.
 *
 * All three are idempotent and safe to call from multiple modules — the
 * bootstrap runs at most once per document for the env + stylesheet, and
 * the editor module promise is naturally cached by the ESM loader.
 */

/**
 * Resolve URLs against this module's location so they work no matter how
 * TYPO3 hashes our `/_assets/<hash>/JavaScript/` path. `import.meta.url`
 * is the canonical anchor — same module, same vendor folder, every time.
 */
const VENDOR_BASE = new URL('./Vendor/monaco/', import.meta.url);

/**
 * Maps Monaco's worker label (passed to `MonacoEnvironment.getWorkerUrl`)
 * to the vendored worker URL. Languages with their own worker (JSON,
 * CSS-family, HTML-family, JS/TS-family) get IntelliSense; everything else
 * falls through to the generic editor worker which provides tokenization
 * only. Labels match what Monaco itself passes — see the docs for the
 * full list, but in practice these five cover every language we ship.
 */
function workerUrlFor(label) {
    switch (label) {
        case 'json':
            return new URL('json.worker.js', VENDOR_BASE).href;
        case 'css':
        case 'scss':
        case 'less':
            return new URL('css.worker.js', VENDOR_BASE).href;
        case 'html':
        case 'handlebars':
        case 'razor':
            return new URL('html.worker.js', VENDOR_BASE).href;
        case 'typescript':
        case 'javascript':
            return new URL('ts.worker.js', VENDOR_BASE).href;
        default:
            return new URL('editor.worker.js', VENDOR_BASE).href;
    }
}

/**
 * One-shot bootstrap for the given document. Idempotent — calling multiple
 * times has no extra effect after the first. Use `doc = this.ownerDocument`
 * when calling from a custom element so the right document gets the
 * stylesheet (BE FormEngine renders inside an iframe whose document is
 * NOT `window.document`).
 *
 * MUST be called before any `monaco.editor.create(...)` runs.
 */
export function ensureBootstrapped(doc = document) {
    // MonacoEnvironment is a global on the worker context Monaco reads at
    // init time. Setting it once before the editor module loads is enough;
    // re-setting is harmless.
    if (!self.MonacoEnvironment) {
        self.MonacoEnvironment = { getWorkerUrl: (_moduleId, label) => workerUrlFor(label) };
    }

    // Inject the editor stylesheet exactly once per document. The marker
    // attribute lets us bail without scanning every <link> in <head>.
    if (!doc.querySelector('link[data-monaco-styles]')) {
        const link = doc.createElement('link');
        link.rel = 'stylesheet';
        link.href = new URL('editor.css', VENDOR_BASE).href;
        link.dataset.monacoStyles = '1';
        doc.head.append(link);
    }
}

/**
 * The Monaco namespace, awaited once and reused across every mount.
 * Importing this module triggers the bundle fetch (~1 MB gz); subsequent
 * imports across other modules share the same promise via the ESM loader.
 */
export const monaco = await import('@ochorocho/revealjs-editor/Vendor/monaco/editor.mjs');

/**
 * Set of language ids Monaco actually shipped grammars for in our bundle.
 * Use to check before calling `monaco.editor.create(..., { language })` —
 * passing an unknown language id makes Monaco throw at runtime. Computed
 * once from `monaco.languages.getLanguages()`, which itself only reflects
 * what was registered at editor.main.js import time (i.e. what's in our
 * vendored editor.mjs).
 */
const KNOWN = new Set(monaco.languages.getLanguages().map((l) => l.id));

/**
 * `true` if Monaco has a grammar for the given language id. Mount sites
 * use this to fall back to plaintext for languages we don't ship (e.g.
 * our custom `typoscript` value).
 */
export function isKnownLanguage(id) {
    return KNOWN.has(id);
}

/**
 * Disable Monaco's built-in find widget on the given editor instance —
 * the widget that opens via Cmd/Ctrl+F.
 *
 * Monaco has no single editor option that removes the widget; it's
 * registered as a permanent editor contribution. The supported way to
 * silence it per-instance is to bind no-op commands over the keys that
 * trigger it, so the keybinding service short-circuits before the
 * contribution sees the event. The widget is still reachable via the
 * command palette (F1) — that's an IDE entry point most slide / BE-form
 * users won't accidentally hit.
 *
 * Called from each mount site immediately after `monaco.editor.create()`.
 * To re-enable per surface, just drop the call.
 */
export function suppressFindWidget(editor) {
    const noop = () => null;
    // Cmd/Ctrl+F — Find
    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyF, noop);
    // Cmd/Ctrl+H — Replace
    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyH, noop);
    // F3 — Find next
    editor.addCommand(monaco.KeyCode.F3, noop);
    // Shift+F3 — Find previous
    editor.addCommand(monaco.KeyMod.Shift | monaco.KeyCode.F3, noop);
    // Cmd/Ctrl+G — Find next (Mac convention)
    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyG, noop);
}