// Vendors mermaid.js as a single self-contained ESM bundle, mirroring the
// "commit the built asset" convention used for Monaco and reveal.js. Run once
// per mermaid version bump and commit the result:
//
//   npm install mermaid
//   npm run build:mermaid
//
// Output: Resources/Public/JavaScript/Vendor/mermaid/mermaid.mjs
// Consumed by MermaidBootstrap.js via the @ochorocho/revealjs-editor importmap.
//
// NB: this script lives at the repo root (NOT under Build/, which is git-ignored
// as the DDEV/TYPO3 dev-install root) so it is committed and survives.

import { build } from 'esbuild';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';

const OUT_DIR = 'Resources/Public/JavaScript/Vendor/mermaid';
const ENTRY = 'node_modules/.cache/revealjs-mermaid-entry.mjs';

mkdirSync(OUT_DIR, { recursive: true });
mkdirSync('node_modules/.cache', { recursive: true });

// Tiny entry that re-exports mermaid's default export. esbuild inlines mermaid's
// dynamic per-diagram imports into the single bundle (splitting disabled), so the
// result is one self-contained file with no runtime chunk fetches.
writeFileSync(ENTRY, "export { default } from 'mermaid';\n");

await build({
    entryPoints: [ENTRY],
    bundle: true,
    format: 'esm',
    minify: true,
    legalComments: 'none',
    target: ['es2020'],
    outfile: `${OUT_DIR}/mermaid.mjs`,
    logLevel: 'info',
});

rmSync(ENTRY, { force: true });
console.log('[build:mermaid] wrote ' + OUT_DIR + '/mermaid.mjs');
