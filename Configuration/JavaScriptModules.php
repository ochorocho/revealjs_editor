<?php

/**
 * Frontend importmap registration for this extension.
 *
 * Today there's exactly one entry — the `<reveal-editable-codemirror>` bridge
 * that wires the BE codemirror element to visual_editor's dataHandlerStore
 * for in-edit-mode save-back of code-slide content. The module specifier is
 * `@ochorocho/revealjs-editor/<path>.js`; the file system path is mapped to
 * the public Resources folder so TYPO3's AssetCollector can publish the
 * built asset URL through `/_assets/<hash>/...`.
 *
 * `dependencies: ['core']` because the bridge imports
 * `@typo3/visual-editor/Frontend/stores/data-handler-store.js` which in turn
 * depends on the `core` package's importmap entries; the `visual-editor`
 * package's own modules are pulled in transitively when the bridge is
 * loaded by `RevealJsCodeMirrorAssetsProcessor` (only in edit mode).
 */
return [
    'dependencies' => ['core'],
    'imports' => [
        '@ochorocho/revealjs-editor/' => 'EXT:revealjs_editor/Resources/Public/JavaScript/',
    ],
];
