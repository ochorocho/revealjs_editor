/**
 * <reveal-editable-codemirror>
 *
 * Bridges a wrapped <typo3-t3editor-codemirror> element to visual_editor's
 * dataHandlerStore. Used by Container/Code.html on the in-edit-mode branch
 * (when ?editMode=1 AND a BE user is logged in — see
 * RevealJsCodeMirrorAssetsProcessor for the gate that controls whether this
 * module gets included in the FE importmap at all).
 *
 * Usage in Fluid:
 *   <reveal-editable-codemirror
 *       table="tx_revealjseditor_codeblock"
 *       uid="{block.data.uid}"
 *       field="code">
 *       <typo3-t3editor-codemirror ... nolazyload autoheight>
 *           <textarea>...code...</textarea>
 *       </typo3-t3editor-codemirror>
 *   </reveal-editable-codemirror>
 *
 * How the save-back works:
 *   - The BE codemirror element installs an EditorView.updateListener that,
 *     on every doc change, rewrites the inner <textarea>'s `value` and
 *     dispatches a native `change` event on it
 *     (cms-backend/.../code-editor/element/code-mirror-element.js).
 *   - We listen to that `change` event and forward the new value to
 *     dataHandlerStore.setData(table, uid, field, value).
 *   - visual_editor's BE toolbar save button then POSTs the accumulated
 *     dataHandlerStore.data via DataHandlerService::run(), which delegates
 *     to TYPO3's DataHandler. The row at tx_revealjseditor_codeblock.uid
 *     gets its `code` column updated. Same code path as a BE form save.
 *
 * No Lit, no template — just a plain custom element. Total surface area is
 * small enough that pulling in Lit's reactive scaffolding isn't worth it.
 */
import { dataHandlerStore } from '@typo3/visual-editor/Frontend/stores/data-handler-store.js';

class RevealEditableCodemirror extends HTMLElement {
    connectedCallback() {
        const tryBind = () => {
            const textarea = this.querySelector('typo3-t3editor-codemirror > textarea');
            if (!textarea) {
                requestAnimationFrame(tryBind);
                return;
            }
            this.#bind(textarea);
        };
        tryBind();
    }

    #bind(textarea) {
        const table = this.getAttribute('table');
        const uid = Number(this.getAttribute('uid'));
        const field = this.getAttribute('field');
        if (!table || !uid || !field) {
            console.warn('[reveal-editable-codemirror] missing table/uid/field; not wiring save-back', this);
            return;
        }

        // Seed the store with the initial value so visual_editor can later
        // detect "no change vs. reverted to original" correctly.
        dataHandlerStore.setInitialData(table, uid, field, textarea.value);

        textarea.addEventListener('change', () => {
            dataHandlerStore.setData(table, uid, field, textarea.value);
        });
    }
}

customElements.define('reveal-editable-codemirror', RevealEditableCodemirror);
