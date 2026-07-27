/**
 * Mermaid bootstrap — single source of truth for "how does mermaid load and
 * render". Both mermaid surfaces in this extension import from here:
 *   - RevealMermaidMount.js       → FE deck, one diagram per
 *                                    `<pre class="mermaid" data-mermaid>` block.
 *   - MermaidFormEngineElement.js → BE FormEngine live preview pane.
 *
 * Mermaid is vendored as one self-contained ESM bundle under Vendor/mermaid/
 * (built by build-mermaid.mjs). Unlike Monaco it has no web workers, no
 * separate stylesheet and no icon font, so this bootstrap is small.
 *
 * SECURITY: mermaid renders editor-supplied diagram source, so it is
 * initialised with `securityLevel: 'strict'` — the default that encodes HTML
 * in labels and disables click-JS. Strict mode sanitises its own SVG output
 * (internal DOMPurify), which is why injecting that SVG via innerHTML below is
 * safe. Never switch this to 'loose'/'antiscript' — that has documented stored
 * XSS.
 */

import mermaid from '@ochorocho/revealjs-editor/Vendor/mermaid/mermaid.mjs';

export { mermaid };

// The deck themes are dark (anthracite / brown backgrounds), and the BE preview
// pane is given a dark background too, so the diagrams look the same in the
// editor and on the slide. mermaid.initialize is global + one-shot.
let initialised = false;
export function ensureInitialised(theme = 'dark') {
    if (initialised) {
        return;
    }
    mermaid.initialize({
        startOnLoad: false,
        securityLevel: 'strict',
        theme,
    });
    initialised = true;
}

// Unique, deterministic id per render — mermaid.render() temporarily inserts a
// node with this id into the DOM, so collisions must be avoided.
let seq = 0;

/**
 * Render `source` as a mermaid diagram into `el` (replacing its contents).
 * On a parse/render error, shows the message as inert text instead of
 * throwing, so one bad diagram never breaks the deck or the BE form.
 *
 * @param {HTMLElement} el
 * @param {string} source
 * @returns {Promise<void>}
 */
export async function renderInto(el, source) {
    ensureInitialised();
    const trimmed = (source ?? '').trim();
    if (trimmed === '') {
        el.replaceChildren();
        return;
    }
    const id = 'revealjs-mermaid-' + (++seq);
    try {
        const { svg } = await mermaid.render(id, trimmed);
        // Strict-mode SVG is sanitised by mermaid; safe to inject.
        el.innerHTML = svg;
    } catch (err) {
        el.replaceChildren();
        const pre = el.ownerDocument.createElement('pre');
        pre.className = 'mermaid-error';
        pre.textContent = 'Mermaid error: ' + (err && err.message ? err.message : String(err));
        el.append(pre);
    }
}
