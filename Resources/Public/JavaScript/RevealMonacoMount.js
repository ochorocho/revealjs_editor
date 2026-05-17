/**
 * Monaco mount for reveal.js code blocks.
 *
 * Loaded by RevealJsMonacoAssetsProcessor on every doktype-1731 page —
 * both for anonymous viewers AND for visual_editor's preview iframe.
 * The same module handles both: the template stamps `data-editable="1"`
 * onto blocks rendered in edit mode, which flips Monaco from read-only
 * to editable + wires the value into visual_editor's `dataHandlerStore`
 * for save-back via TYPO3's DataHandler.
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
 *
 * The Monaco engine itself — worker-URL wiring, editor.css injection, the
 * shared `monaco` namespace — lives in MonacoBootstrap.js so the FE path
 * and the BE-form path stay byte-identical.
 */
import { monaco, ensureBootstrapped, isKnownLanguage, suppressFindWidget } from './MonacoBootstrap.js';

ensureBootstrapped(document);

/**
 * Walks `.reveal pre[data-monaco]` blocks beneath `root` and mounts a
 * Monaco editor in each. Idempotent — blocks already promoted carry
 * `data-monaco-mounted="1"` and are skipped.
 */
function mountAll(root = document) {
    const blocks = root.querySelectorAll('.reveal pre[data-monaco]:not([data-monaco-mounted])');
    blocks.forEach((pre) => {
        pre.dataset.monacoMounted = '1';
        mountOne(pre);
    });
}

function mountOne(pre) {
    const requested = pre.dataset.language || 'plaintext';
    const language = isKnownLanguage(requested) ? requested : 'plaintext';
    const value = pre.textContent ?? '';
    const editable = pre.dataset.editable === '1';

    // Clear the seed text; Monaco fills the container with its own DOM.
    pre.textContent = '';

    const editor = monaco.editor.create(pre, {
        value,
        language,
        theme: 'vs-dark',
        readOnly: !editable,
        automaticLayout: true,
        // Slide-friendly chrome — strip the IDE-isms that don't belong on
        // a presentation slide. Editors can still find/replace (Cmd+F),
        // suggest (Ctrl+Space), and select/edit lines normally.
        minimap: { enabled: false },
        scrollBeyondLastLine: false,
        lineNumbers: 'on',
		glyphMargin: false,
        folding: true,
        lineDecorationsWidth: 0,
        lineNumbersMinChars: 0,
        wordWrap: 'off',
        contextmenu: editable,
        renderLineHighlight: editable ? 'line' : 'none',
        fontFamily: 'var(--r-code-font, Menlo, Monaco, "Courier New", monospace)',
        // Slide context: bigger than Monaco's 14px default so code is
        // legible from the back of the room. Bump or drop as needed.
        fontSize: 18,
        // Cmd+Wheel zoom would fight the slide's transform: scale().
        mouseWheelZoom: false,
    });

    suppressFindWidget(editor);

    // Auto-height: drive the `<pre>` container's height from the editor's
    // natural content height. Monaco's `automaticLayout` only handles
    // container-resize-driven re-layout, not content-driven sizing.
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
 * Lazy-imported: the visual_editor bundle only exists when the page is
 * being served behind the edit-mode gate; anonymous viewers would 404 if
 * we top-level-imported it.
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

// Mount on initial page load. `domcontentloaded` is the right hook — the
// <pre> blocks are server-rendered, so they're in the DOM by then.
const ready = document.readyState === 'loading'
    ? new Promise((r) => document.addEventListener('DOMContentLoaded', r, { once: true }))
    : Promise.resolve();
ready.then(() => mountAll());

// Targeted re-mount hook for callers that inject new blocks into the DOM
// after initial load (e.g. visual_editor reloading a slide's content area
// after a save). Dispatch `new CustomEvent('monaco:refresh', { detail:
// { root: <element> } })` on any element to limit the sweep to that
// subtree; fires `mountAll(document)` if no detail is provided.
document.addEventListener('monaco:refresh', (event) => {
    const detail = /** @type {CustomEvent} */ (event).detail ?? {};
    mountAll(detail.root ?? event.target ?? document);
});
