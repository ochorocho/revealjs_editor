<?php

/**
 * Frontend importmap registration for this extension.
 *
 * Maps the prefix `@ochorocho/revealjs-editor/...` to the public asset
 * tree at `Resources/Public/JavaScript/...`. TYPO3's AssetCollector
 * publishes the path through `/_assets/<hash>/JavaScript/...`. The
 * `core` dependency is needed because `MonacoFormEngineElement.js`
 * lazy-imports `@typo3/visual-editor/Frontend/stores/data-handler-store.js`
 * in edit mode, which depends on the `core` package's importmap entries.
 */
return [
    'dependencies' => ['core'],
    'imports' => [
        '@ochorocho/revealjs-editor/' => 'EXT:revealjs_editor/Resources/Public/JavaScript/',
    ],
];
