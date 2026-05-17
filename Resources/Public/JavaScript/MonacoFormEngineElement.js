/**
 * <typo3-monaco-editor>
 *
 * Custom element used by Classes/Form/Element/MonacoEditorElement.php
 * (TYPO3 FormEngine renderType="monacoEditor") to mount a Monaco editor
 * inside the BE form for the `tx_revealjseditor_codeblock.code` column.
 * Replaces the previous plain `<textarea>`.
 *
 * Markup contract (emitted by MonacoEditorElement::render()):
 *
 *   <typo3-monaco-editor language="X" value-target="<select.…>">
 *       <textarea name="data[…][…][…][code]" hidden>RAW</textarea>
 *   </typo3-monaco-editor>
 *
 * The inner `<textarea hidden>` is BOTH the seed (carries the initial
 * value through HTML-escape round-tripping) AND the FormEngine wire (its
 * `name` attribute is what FormEngine's POST handler reads on save).
 * On every editor change we write the value back into the textarea so
 * the standard form save picks it up — no custom DataHandler glue.
 *
 * Architecture: this element shares the same vendored Monaco bundle
 * with the FE-side RevealMonacoMount.js (Vendor/monaco/editor.mjs).
 * Loading the BE form on a code-slide will fetch ~1.2 MB gzipped on
 * first visit; subsequent BE form opens come from the asset cache.
 *
 * MonacoEnvironment.getWorkerUrl is set the same way as the FE module
 * — workers are resolved against the vendored Vendor/monaco/ folder.
 * The browser only spins one Monaco worker process; multiple
 * `<typo3-monaco-editor>` instances on a single form (one per IRRE
 * child row) all share the same worker.
 */

const VENDOR_BASE = new URL('./Vendor/monaco/', import.meta.url);

self.MonacoEnvironment = self.MonacoEnvironment ?? {
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

function ensureEditorStylesheet(doc) {
    if (doc.querySelector('link[data-monaco-styles]')) return;
    const link = doc.createElement('link');
    link.rel = 'stylesheet';
    link.href = new URL('editor.css', VENDOR_BASE).href;
    link.dataset.monacoStyles = '1';
    doc.head.append(link);
}

const monacoPromise = import('@ochorocho/revealjs-editor/Vendor/monaco/editor.mjs');

class TypO3MonacoEditor extends HTMLElement {
    /** @type {import('monaco-editor').editor.IStandaloneCodeEditor | null} */
    #editor = null;
    /** @type {HTMLTextAreaElement | null} */
    #textarea = null;
    /** @type {HTMLDivElement | null} */
    #container = null;
    /** @type {boolean} */
    #mounted = false;

    connectedCallback() {
        // FormEngine sometimes re-attaches inline-IRRE children when the
        // editor expands a collapsed accordion row; guard against double
        // mount.
        if (this.#mounted) return;
        this.#textarea = this.querySelector(':scope > textarea');
        if (!this.#textarea) {
            console.warn('[typo3-monaco-editor] no inner <textarea> found; not mounting', this);
            return;
        }
        this.#mounted = true;
        ensureEditorStylesheet(this.ownerDocument);

        // Build the container the editor mounts into. Monaco needs a
        // sized div; the host element provides the outer box via CSS.
        this.#container = this.ownerDocument.createElement('div');
        this.#container.className = 'typo3-monaco-editor-host';
        this.#container.style.cssText = 'width:100%;min-height:280px;height:280px;';
        this.append(this.#container);

        this.#mount().catch((err) => {
            console.error('[typo3-monaco-editor] mount failed', err);
            this.#mounted = false;
        });
    }

    async #mount() {
        const monaco = await monacoPromise;
        const rawLanguage = this.getAttribute('language') || 'plaintext';
        const knownLanguages = new Set(monaco.languages.getLanguages().map((l) => l.id));
        const language = knownLanguages.has(rawLanguage) ? rawLanguage : 'plaintext';

        this.#editor = monaco.editor.create(this.#container, {
            value: this.#textarea.value,
            language,
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: false },
            scrollBeyondLastLine: false,
            // BE FormEngine context — keep line numbers ON (editors typing
            // real code benefit from line refs; vs the slide context
            // where line numbers eat horizontal space).
            lineNumbers: 'on',
            folding: true,
            wordWrap: 'on',
            tabSize: 4,
            insertSpaces: true,
            mouseWheelZoom: false,
        });

        // FormEngine reads the textarea's `value` on POST. Write through
        // on every editor change so the save handler picks up the latest.
        this.#editor.onDidChangeModelContent(() => {
            this.#textarea.value = this.#editor.getValue();
            // Dispatch a `change` event so FormEngine's own change-tracking
            // (the "unsaved changes" indicator etc.) notices the edit.
            this.#textarea.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    disconnectedCallback() {
        // FormEngine moves inline-IRRE children around when sorting; we
        // keep the editor instance alive across detach + immediate
        // re-attach (same tick) so the user's cursor / selection / undo
        // stack survives. If the host is genuinely removed (form close),
        // GC cleans up; Monaco editors don't leak as long as the
        // container reference goes too.
    }
}

customElements.define('typo3-monaco-editor', TypO3MonacoEditor);
