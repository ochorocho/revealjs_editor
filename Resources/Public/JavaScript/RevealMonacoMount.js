/**
 * Monaco mount for reveal.js code blocks.
 *
 * Loaded by RevealJsMonacoAssetsProcessor on EVERY doktype-1731 page —
 * both for anonymous viewers AND for visual_editor's preview iframe.
 * The same module handles both: the template stamps `data-editable="1"`
 * onto blocks rendered in edit mode, which flips Monaco from read-only
 * to editable + wires the value into visual_editor's `dataHandlerStore`
 * for save-back via TYPO3's DataHandler.
 *
 * What this ships to the browser:
 *   - editor.mjs (~1.08 MB gz): main editor + all language tokenizers
 *     + vs / vs-dark / hc themes + every Monaco UI contribution
 *   - editor.css (~22 KB gz) + codicon.ttf (~48 KB gz): editor chrome
 *   - editor.worker.js (~86 KB gz): core editor worker (always)
 *   - ts/html/css/json.worker.js (123–1345 KB gz each): lazy per language
 *
 * Per-language workers are fetched on demand when Monaco first sees a
 * block in that language. Anonymous viewers of a PHP-only deck won't
 * pull the TS worker, etc. The main bundle still ships on every view.
 *
 * Markup contract (Container/Code.html):
 *
 *   <pre data-monaco
 *        data-language="X"
 *        data-table="tx_revealjseditor_codeblock"
 *        data-uid="123"
 *        data-field="code"
 *        data-editable="0|1">RAW SOURCE</pre>
 *
 * On connect, we read the raw source from `pre.textContent`, blank the
 * `<pre>`, and let Monaco take over the container.
 */

// MonacoEnvironment MUST be set BEFORE the editor.mjs import resolves —
// Monaco's bootstrap reads it at module-init time to spin up the core
// editor worker. We compute worker URLs against import.meta.url so they
// resolve against the actual location of this module (TYPO3's hashed
// /_assets/<hash>/JavaScript/... URL), not against the page origin.
const VENDOR_BASE = new URL('./Vendor/monaco/', import.meta.url);

self.MonacoEnvironment = {
    getWorkerUrl(_moduleId, label) {
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
    },
};

// Monaco's chrome (carets, scrollbars, completion popup, codicon font)
// lives in a sibling editor.css. Inject a <link> tag once per document
// so it's available before the first mount. Idempotent — guards via a
// marker class on the link element.
function ensureEditorStylesheet(doc) {
    if (doc.querySelector('link[data-monaco-styles]')) return;
    const link = doc.createElement('link');
    link.rel = 'stylesheet';
    link.href = new URL('editor.css', VENDOR_BASE).href;
    link.dataset.monacoStyles = '1';
    doc.head.append(link);
}
ensureEditorStylesheet(document);

// One Monaco instance shared across the page (the import resolves once;
// every mountAll() pass reuses the same monaco namespace). The import
// is async because it pulls a ~1.08 MB gzipped file; mount calls await
// it before touching any blocks.
const monacoPromise = import('@ochorocho/revealjs-editor/Vendor/monaco/editor.mjs');

/**
 * Walks `.reveal pre[data-monaco]` blocks beneath `root` and mounts a
 * Monaco editor in each. Idempotent — blocks that have already been
 * mounted carry `data-monaco-mounted="1"` and are skipped.
 */
async function mountAll(root = document) {
    const monaco = await monacoPromise;
    const blocks = root.querySelectorAll('.reveal pre[data-monaco]:not([data-monaco-mounted])');
    blocks.forEach((pre) => {
        pre.dataset.monacoMounted = '1';
        mountOne(pre, monaco);
    });
}

function mountOne(pre, monaco) {
    const rawLanguage = pre.dataset.language || 'plaintext';
    const value = pre.textContent ?? '';
    const editable = pre.dataset.editable === '1';

    // Validate the language against Monaco's registered set. Unknown
    // languages (e.g. our `typoscript` value — no Monaco grammar) fall
    // back to plaintext rendering. Monaco's `getLanguages()` returns the
    // currently registered language descriptors.
    const knownLanguages = new Set(monaco.languages.getLanguages().map((l) => l.id));
    const language = knownLanguages.has(rawLanguage) ? rawLanguage : 'plaintext';

    // Clear the seed text; Monaco fills the container with its own DOM.
    pre.textContent = '';

    const editor = monaco.editor.create(pre, {
        value,
        language,
        theme: 'vs-dark',
        readOnly: !editable,
        automaticLayout: true,
        // Slide-friendly chrome — strip the IDE-isms that don't belong
        // on a presentation slide. Editors can still find/replace
        // (Cmd+F), suggest (Ctrl+Space), select/edit lines normally.
        minimap: { enabled: false },
        scrollBeyondLastLine: false,
        lineNumbers: 'off',
        glyphMargin: false,
        folding: false,
        lineDecorationsWidth: 0,
        lineNumbersMinChars: 0,
        wordWrap: 'off',
        // contextmenu is the right-click menu — disable for read-only so
        // viewers can't accidentally trigger an empty IDE menu.
        contextmenu: editable,
        renderLineHighlight: editable ? 'line' : 'none',
        // Use reveal's --r-code-font when the theme defines one; falls
        // back to Monaco's default monospace stack otherwise.
        fontFamily: 'var(--r-code-font, Menlo, Monaco, "Courier New", monospace)',
        // Disable mouse-wheel zoom; on a deck slide, Cmd+Wheel zooming
        // text would be confusing and break the layout assumptions of
        // the slide's transform: scale().
        mouseWheelZoom: false,
    });

    // Auto-height: drive the `<pre>` container's height from the
    // editor's natural content height. Monaco doesn't auto-size out of
    // the box — `automaticLayout` only handles container-resize-driven
    // re-layout, not content-driven sizing.
    const syncHeight = () => {
        const h = Math.max(editor.getContentHeight(), 48);
        pre.style.height = h + 'px';
        editor.layout({ width: pre.clientWidth, height: h });
    };
    syncHeight();
    editor.onDidContentSizeChange(syncHeight);

    if (editable) {
        wireDataHandlerStore(editor, pre);
    }
}

/**
 * In edit mode, pipe the editor's value into visual_editor's
 * dataHandlerStore on every change. The store is the bridge between
 * what's in the preview iframe and what TYPO3's DataHandler will
 * persist when the editor hits Save on the BE toolbar.
 *
 * Lazy-imported because the visual_editor bundle only exists when the
 * page is being served behind the edit-mode gate; anonymous viewers
 * would 404 if we top-level-imported it.
 */
async function wireDataHandlerStore(editor, pre) {
    const { table, uid, field } = pre.dataset;
    if (!table || !uid || !field) {
        console.warn('[reveal-monaco] missing data-table/uid/field on editable block', pre);
        return;
    }
    const { dataHandlerStore } = await import('@typo3/visual-editor/Frontend/stores/data-handler-store.js');
    dataHandlerStore.setInitialData(table, Number(uid), field, editor.getValue());
    editor.onDidChangeModelContent(() => {
        dataHandlerStore.setData(table, Number(uid), field, editor.getValue());
    });
}

// Mount on initial page load. `domcontentloaded` is the right hook —
// the <pre> blocks are server-rendered, so they're in the DOM by then.
// We don't wait for `load` because that would block on every image/font.
const ready = document.readyState === 'loading'
    ? new Promise((r) => document.addEventListener('DOMContentLoaded', r, { once: true }))
    : Promise.resolve();
ready.then(() => mountAll());

// Targeted re-mount hook for callers that inject new blocks into the
// DOM after initial load (e.g. visual_editor reloading a slide's content
// area after a save). Dispatch `new CustomEvent('monaco:refresh', {
// detail: { root: <element> } })` on any element to limit the sweep to
// that subtree; fires `mountAll(document)` if no detail is provided.
document.addEventListener('monaco:refresh', (event) => {
    const detail = /** @type {CustomEvent} */ (event).detail ?? {};
    mountAll(detail.root ?? event.target ?? document);
});
