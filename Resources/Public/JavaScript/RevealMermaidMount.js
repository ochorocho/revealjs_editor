/**
 * FE deck mermaid mount. Renders every `<pre class="mermaid" data-mermaid>`
 * block into an SVG diagram.
 *
 * Called from the init `<script>` in Pages/Revealjs.html (lazily — only when a
 * `[data-mermaid]` block exists, so mermaid's ~3 MB bundle never loads on decks
 * without diagrams), passing the live Reveal instance:
 *
 *   const { mountMermaid } = await import('.../RevealMermaidMount.js');
 *   mountMermaid(Reveal);
 *
 * WHY hook Reveal events instead of DOMContentLoaded: reveal.js sets
 * `display:none` on slides beyond `viewDistance`, and a diagram rendered while
 * its slide is hidden comes out zero/wrong-sized (a well-known reveal+mermaid
 * issue). So each block is rendered the first time its slide is actually
 * visible — on `ready` (initial slides) and `slidechanged` (the rest). Once the
 * SVG lands, the per-slide ResizeObserver in Pages/Revealjs.html re-scales the
 * slide to fit.
 */
import { renderInto } from './MermaidBootstrap.js';

/**
 * @param {{ on: (type: string, cb: () => void) => void }} Reveal
 */
export function mountMermaid(Reveal) {
    const renderVisible = () => {
        const blocks = document.querySelectorAll('.reveal [data-mermaid]:not([data-mermaid-rendered])');
        blocks.forEach((el) => {
            // Skip blocks whose slide is display:none (offsetParent null and no
            // client rects) — they'll render when navigated to.
            if (el.offsetParent === null && el.getClientRects().length === 0) {
                return;
            }
            // Read the raw source (entity-decoded) BEFORE we overwrite the
            // block's contents with the rendered SVG. Render once per block.
            const source = el.textContent ?? '';
            el.dataset.mermaidRendered = '1';
            renderInto(el, source);
        });
    };

    Reveal.on('ready', renderVisible);
    Reveal.on('slidechanged', renderVisible);
    // `ready` may have already fired before this handler attached (initialize
    // resolves after emitting it), so trigger one pass on the next frame.
    requestAnimationFrame(renderVisible);
}
