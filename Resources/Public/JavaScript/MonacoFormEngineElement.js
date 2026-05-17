/**
 * <typo3-monaco-editor>
 *
 * Custom element used by Classes/Form/Element/MonacoEditorElement.php
 * (TYPO3 FormEngine renderType="monacoEditor") to mount a Monaco editor
 * inside the BE form for the `tx_revealjseditor_codeblock.code` column.
 *
 * Markup contract (emitted by MonacoEditorElement::render()):
 *
 *   <typo3-monaco-editor language="X">
 *       <textarea name="data[…][…][…][code]" hidden style="display: none;">RAW</textarea>
 *   </typo3-monaco-editor>
 *
 * The inner `<textarea hidden>` is BOTH the seed (carries the initial
 * value through HTML-escape round-tripping) AND the FormEngine wire (its
 * `name` attribute is what FormEngine's POST handler reads on save).
 * On every editor change we write the value back into the textarea so
 * the standard form save picks it up — no custom DataHandler glue.
 *
 * The Monaco engine itself — worker-URL wiring, editor.css injection, the
 * shared `monaco` namespace — lives in MonacoBootstrap.js so this module
 * and the FE-side RevealMonacoMount.js stay byte-identical at the engine
 * level. We call `ensureBootstrapped(this.ownerDocument)` (not `document`)
 * because the BE form renders inside an iframe whose document is NOT
 * window.document; the stylesheet needs to land in the right <head>.
 */
import { monaco, ensureBootstrapped, isKnownLanguage, suppressFindWidget } from './MonacoBootstrap.js';

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
        ensureBootstrapped(this.ownerDocument);

        // Build the container the editor mounts into. Monaco needs a
        // sized div; the host element provides the outer box via CSS.
        this.#container = this.ownerDocument.createElement('div');
        this.#container.className = 'typo3-monaco-editor-host';
        this.#container.style.cssText = 'width:100%;min-height:280px;height:280px;';
        this.append(this.#container);

        this.#mount();
    }

    #mount() {
        const requested = this.getAttribute('language') || 'plaintext';
        const language = isKnownLanguage(requested) ? requested : 'plaintext';

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
            // BE form context: match Monaco's default (14px) — same size
            // editors get in VS Code, which is the muscle memory they
            // come in with. Bump if forms feel cramped.
            fontSize: 14,
        });

        suppressFindWidget(this.#editor);

        // FormEngine reads the textarea's `value` on POST. Write through on
        // every editor change so the save handler picks up the latest.
        this.#editor.onDidChangeModelContent(() => {
            this.#textarea.value = this.#editor.getValue();
            // Dispatch `change` so FormEngine's own change-tracking (the
            // "unsaved changes" indicator etc.) notices the edit.
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