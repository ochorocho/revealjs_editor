/**
 * <typo3-mermaid-editor>
 *
 * Custom element used by Classes/Form/Element/MermaidEditorElement.php
 * (TYPO3 FormEngine renderType="mermaidEditor") to give editors a live
 * diagram editor inside the BE form for the `tx_revealjseditor_mermaid` column.
 *
 * Markup contract (emitted by MermaidEditorElement::render()):
 *
 *   <typo3-mermaid-editor>
 *       <textarea name="data[…][…][tx_revealjseditor_mermaid]" hidden>RAW</textarea>
 *   </typo3-mermaid-editor>
 *
 * Split pane: a Monaco editor (reusing MonacoBootstrap — same engine as the
 * code-block editor) on the left, a live mermaid preview on the right. The
 * inner `<textarea hidden>` is the FormEngine POST carrier; we write the
 * editor value back into it on every change so the standard form save picks it
 * up (no custom DataHandler glue). The preview re-renders debounced.
 *
 * The split pane fills the remaining viewport height (recomputed on resize and
 * when the field's tab becomes visible), and the preview supports pan/zoom
 * (buttons, Ctrl/Cmd + wheel, drag) like the Mermaid Live Editor.
 *
 * `ensureBootstrapped(this.ownerDocument)` targets the element's OWN document
 * because the BE form renders inside an iframe (Monaco's stylesheet must land
 * in that iframe's <head>).
 */
import { monaco, ensureBootstrapped, suppressFindWidget } from './MonacoBootstrap.js';
import { renderInto } from './MermaidBootstrap.js';
import { MERMAID_EXAMPLES } from './MermaidExamples.js';

const PREVIEW_DEBOUNCE_MS = 300;
const MIN_ROW_HEIGHT = 360;
const ZOOM_MIN = 0.2;
const ZOOM_MAX = 6;
const ZOOM_STEP = 1.2;

class Typo3MermaidEditor extends HTMLElement {
    /** @type {import('monaco-editor').editor.IStandaloneCodeEditor | null} */
    #editor = null;
    /** @type {HTMLTextAreaElement | null} */
    #textarea = null;
    /** @type {HTMLDivElement | null} */
    #editorPane = null;
    /** @type {HTMLDivElement | null} */
    #previewPane = null;
    /** @type {HTMLDivElement | null} */
    #previewInner = null;
    /** @type {HTMLDivElement | null} */
    #row = null;
    /** @type {HTMLSpanElement | null} */
    #zoomLabel = null;
    /** @type {number} */
    #zoom = 1;
    /** @type {boolean} */
    #mounted = false;
    /** @type {ReturnType<typeof setTimeout> | null} */
    #timer = null;
    /** @type {ResizeObserver | null} */
    #resizeObserver = null;
    /** @type {(() => void) | null} */
    #onWinResize = null;

    connectedCallback() {
        if (this.#mounted) {
            return;
        }
        this.#textarea = this.querySelector(':scope > textarea');
        if (!this.#textarea) {
            console.warn('[typo3-mermaid-editor] no inner <textarea> found; not mounting', this);
            return;
        }
        this.#mounted = true;
        ensureBootstrapped(this.ownerDocument);

        const doc = this.ownerDocument;
        const outer = doc.createElement('div');
        outer.className = 'typo3-mermaid-editor';
        outer.style.cssText = 'display:flex;flex-direction:column;gap:8px;width:100%;';

        outer.append(this.#buildToolbar(doc));

        this.#row = doc.createElement('div');
        this.#row.className = 'typo3-mermaid-editor-row';
        this.#row.style.cssText = 'display:flex;gap:8px;width:100%;height:' + MIN_ROW_HEIGHT + 'px;';

        this.#editorPane = doc.createElement('div');
        this.#editorPane.className = 'typo3-mermaid-editor-code';
        this.#editorPane.style.cssText = 'flex:1 1 50%;min-width:0;height:100%;';

        this.#previewPane = doc.createElement('div');
        this.#previewPane.className = 'typo3-mermaid-editor-preview';
        // Dark preview background (WYSIWYG with the dark deck). overflow:auto +
        // the scaled inner div give pan-by-scroll; position:relative anchors the
        // zoom-control overlay.
        this.#previewPane.style.cssText =
            'flex:1 1 50%;min-width:0;height:100%;overflow:auto;background:#1e1e1e;'
            + 'border-radius:4px;position:relative;cursor:grab;';

        // The render target. Zoom applies here (transform), so it survives
        // renderInto()'s innerHTML replacement; the control overlay is a sibling
        // and is never overwritten.
        this.#previewInner = doc.createElement('div');
        this.#previewInner.className = 'typo3-mermaid-editor-preview-inner';
        this.#previewInner.style.cssText =
            'padding:12px;transform-origin:top center;width:100%;text-align:center;';

        this.#previewPane.append(this.#previewInner, this.#buildZoomControls(doc));
        this.#wirePanAndWheelZoom();

        this.#row.append(this.#editorPane, this.#previewPane);
        outer.append(this.#row);
        this.append(outer);

        this.#mount();
        this.#setupViewportFill();
    }

    disconnectedCallback() {
        if (this.#resizeObserver) {
            this.#resizeObserver.disconnect();
            this.#resizeObserver = null;
        }
        const win = this.ownerDocument?.defaultView;
        if (win && this.#onWinResize) {
            win.removeEventListener('resize', this.#onWinResize);
        }
    }

    /**
     * Toolbar with an "Insert example" picker — the Mermaid Live Editor's
     * "Sample Diagrams" menu. Choosing one replaces the editor content (via
     * executeEdits so Monaco's undo still reverts it).
     */
    #buildToolbar(doc) {
        const toolbar = doc.createElement('div');
        toolbar.className = 'typo3-mermaid-editor-toolbar';
        toolbar.style.cssText = 'display:flex;align-items:center;gap:6px;';

        const label = doc.createElement('label');
        label.textContent = 'Insert example:';
        label.style.cssText = 'font-size:0.85em;display:flex;align-items:center;gap:6px;';

        const select = doc.createElement('select');
        select.className = 'form-select form-select-sm typo3-mermaid-editor-examples';
        select.style.cssText = 'width:auto;';

        const placeholder = doc.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '— choose a diagram —';
        select.append(placeholder);

        MERMAID_EXAMPLES.forEach((example, index) => {
            const option = doc.createElement('option');
            option.value = String(index);
            option.textContent = example.label;
            select.append(option);
        });

        select.addEventListener('change', () => {
            const index = Number(select.value);
            const example = MERMAID_EXAMPLES[index];
            select.value = ''; // reset so the same example can be re-picked
            if (!example || !this.#editor) {
                return;
            }
            const model = this.#editor.getModel();
            this.#editor.executeEdits('mermaid-example', [{
                range: model.getFullModelRange(),
                text: example.code,
            }]);
            this.#editor.setPosition({ lineNumber: 1, column: 1 });
            this.#editor.focus();
        });

        label.append(select);
        toolbar.append(label);
        return toolbar;
    }

    #mount() {
        this.#editor = monaco.editor.create(this.#editorPane, {
            value: this.#textarea.value,
            // Monaco ships no mermaid grammar — plaintext avoids a runtime throw
            // and keeps the diagram source unstyled/legible.
            language: 'plaintext',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: false },
            scrollBeyondLastLine: false,
            lineNumbers: 'on',
            wordWrap: 'on',
            tabSize: 2,
            insertSpaces: true,
            mouseWheelZoom: false,
            fontSize: 14,
        });

        suppressFindWidget(this.#editor);

        this.#editor.onDidChangeModelContent(() => {
            const value = this.#editor.getValue();
            this.#textarea.value = value;
            this.#textarea.dispatchEvent(new Event('change', { bubbles: true }));
            this.#schedulePreview(value);
        });

        // Initial render of the stored diagram.
        this.#schedulePreview(this.#textarea.value);
    }

    #schedulePreview(value) {
        if (this.#timer) {
            clearTimeout(this.#timer);
        }
        this.#timer = setTimeout(() => {
            if (this.#previewInner) {
                renderInto(this.#previewInner, value);
            }
        }, PREVIEW_DEBOUNCE_MS);
    }

    // ----- Viewport-fill height -------------------------------------------

    #setupViewportFill() {
        const win = this.ownerDocument.defaultView;
        if (!win) {
            return;
        }
        this.#onWinResize = () => this.#fitHeight();
        win.addEventListener('resize', this.#onWinResize);
        // The field starts on a hidden (inactive) FormEngine tab; the host has
        // no layout until the tab is shown. A ResizeObserver on the host fires
        // when it gains size, so we recompute the height at the right moment.
        if (typeof win.ResizeObserver === 'function') {
            this.#resizeObserver = new win.ResizeObserver(() => this.#fitHeight());
            this.#resizeObserver.observe(this);
        }
        win.requestAnimationFrame(() => this.#fitHeight());
    }

    #fitHeight() {
        const win = this.ownerDocument.defaultView;
        if (!win || !this.#row) {
            return;
        }
        const top = this.#row.getBoundingClientRect().top;
        // top <= 0 ⇒ hidden tab / not laid out yet; leave the min height.
        if (top <= 0) {
            return;
        }
        const height = Math.max(MIN_ROW_HEIGHT, Math.floor(win.innerHeight - top - 24));
        this.#row.style.height = height + 'px';
    }

    // ----- Preview pan + zoom ---------------------------------------------

    #buildZoomControls(doc) {
        const bar = doc.createElement('div');
        bar.className = 'typo3-mermaid-editor-zoom';
        bar.style.cssText =
            'position:absolute;top:6px;right:6px;display:flex;gap:2px;align-items:center;'
            + 'background:rgba(0,0,0,0.45);border-radius:4px;padding:2px;';

        const mkBtn = (glyph, title, onClick) => {
            const b = doc.createElement('button');
            b.type = 'button';
            b.textContent = glyph;
            b.title = title;
            b.style.cssText =
                'border:0;background:transparent;color:#fff;cursor:pointer;'
                + 'width:24px;height:24px;line-height:1;font-size:14px;border-radius:3px;';
            // Keep clicks from starting a pan-drag on the pane behind the bar.
            b.addEventListener('mousedown', (e) => e.stopPropagation());
            b.addEventListener('click', (e) => { e.preventDefault(); onClick(); });
            return b;
        };

        this.#zoomLabel = doc.createElement('span');
        this.#zoomLabel.className = 'typo3-mermaid-editor-zoom-label';
        this.#zoomLabel.style.cssText = 'color:#fff;font-size:11px;min-width:34px;text-align:center;cursor:pointer;';
        this.#zoomLabel.title = 'Reset zoom';
        this.#zoomLabel.textContent = '100%';
        this.#zoomLabel.addEventListener('mousedown', (e) => e.stopPropagation());
        this.#zoomLabel.addEventListener('click', () => this.#setZoom(1));

        bar.append(
            mkBtn('−', 'Zoom out', () => this.#setZoom(this.#zoom / ZOOM_STEP)),
            this.#zoomLabel,
            mkBtn('+', 'Zoom in', () => this.#setZoom(this.#zoom * ZOOM_STEP)),
        );
        return bar;
    }

    #wirePanAndWheelZoom() {
        const pane = this.#previewPane;

        // Ctrl/Cmd + wheel zooms; plain wheel scrolls (pans) natively.
        pane.addEventListener('wheel', (e) => {
            if (!(e.ctrlKey || e.metaKey)) {
                return;
            }
            e.preventDefault();
            this.#setZoom(e.deltaY < 0 ? this.#zoom * ZOOM_STEP : this.#zoom / ZOOM_STEP);
        }, { passive: false });

        // Drag-to-pan via scroll position.
        let dragging = false;
        let sx = 0;
        let sy = 0;
        let left = 0;
        let top = 0;
        pane.addEventListener('mousedown', (e) => {
            dragging = true;
            sx = e.clientX;
            sy = e.clientY;
            left = pane.scrollLeft;
            top = pane.scrollTop;
            pane.style.cursor = 'grabbing';
        });
        const stop = () => { dragging = false; pane.style.cursor = 'grab'; };
        pane.addEventListener('mousemove', (e) => {
            if (!dragging) {
                return;
            }
            pane.scrollLeft = left - (e.clientX - sx);
            pane.scrollTop = top - (e.clientY - sy);
        });
        pane.addEventListener('mouseup', stop);
        pane.addEventListener('mouseleave', stop);
    }

    #setZoom(zoom) {
        this.#zoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, zoom));
        if (this.#previewInner) {
            this.#previewInner.style.transform = 'scale(' + this.#zoom + ')';
        }
        if (this.#zoomLabel) {
            this.#zoomLabel.textContent = Math.round(this.#zoom * 100) + '%';
        }
    }
}

customElements.define('typo3-mermaid-editor', Typo3MermaidEditor);
