<?php

declare(strict_types=1);

defined('TYPO3') or die();

$ll = 'LLL:EXT:revealjs_editor/Resources/Private/Language/locallang_db.xlf';

/*
 * Inline child of tt_content for the `revealjs_slide_code` CType.
 * One row per code block on a code-slide: headline + language + code.
 *
 * Parent linkage: `parent_uid` holds the parent tt_content uid; the parent's
 * type=inline column declares `foreign_field = parent_uid` so FormEngine
 * resolves the collection automatically.
 *
 * Schema: the analyser creates this table from the TCA below. No SQL needed.
 *   uid, pid, tstamp, crdate, deleted, hidden, sorting, sys_language_uid,
 *   l10n_parent, l10n_diffsource, l10n_source     — standard control columns
 *   parent_uid (int)                              — FK back to tt_content.uid
 *   headline (varchar)                            — h3 label rendered above the code
 *   language (varchar)                            — drives <code class="language-X">
 *   code (longtext)                               — the source itself
 */
return [
    'ctrl' => [
        'title' => $ll . ':tx_revealjseditor_codeblock',
        'label' => 'headline',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource' => 'l10n_source',
        // tt_content is workspace-aware; the schema migrator otherwise
        // synthesises this flag at bootstrap with a deprecation warning.
        'versioningWS' => true,
        // Never list at the root of the BE — these rows only make sense
        // inside their parent tt_content collection.
        'hideTable' => true,
        'security' => ['ignorePageTypeRestriction' => true],
        'typeicon_classes' => ['default' => 'content-revealjs-slide-code'],
    ],
    'columns' => [
        // FK back to the parent tt_content row. type=passthrough hides it
        // from FormEngine; FormEngine fills it automatically when the row
        // is created inside the inline collection (foreign_field plumbing).
        'parent_uid' => [
            'config' => ['type' => 'passthrough'],
        ],
        'headline' => [
            'label' => $ll . ':tx_revealjseditor_codeblock.headline',
            'config' => [
                'type' => 'input',
                'size' => 60,
                'eval' => 'trim',
            ],
        ],
        'language' => [
            'label' => $ll . ':tx_revealjseditor_codeblock.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 'plaintext',
                // Values match the `language-X` class highlight.js expects.
                // The label set is intentionally short — covers ~95% of
                // real-world deck use cases without overwhelming the editor.
                'items' => [
                    ['label' => $ll . ':tx_revealjseditor_codeblock.language.plaintext', 'value' => 'plaintext'],
                    ['label' => 'PHP', 'value' => 'php'],
                    ['label' => 'JavaScript', 'value' => 'javascript'],
                    ['label' => 'TypeScript', 'value' => 'typescript'],
                    ['label' => 'HTML', 'value' => 'html'],
                    ['label' => 'CSS', 'value' => 'css'],
                    ['label' => 'JSON', 'value' => 'json'],
                    ['label' => 'YAML', 'value' => 'yaml'],
                    ['label' => 'XML', 'value' => 'xml'],
                    ['label' => 'SQL', 'value' => 'sql'],
                    ['label' => 'Bash', 'value' => 'bash'],
                    ['label' => 'TypoScript', 'value' => 'typoscript'],
                ],
            ],
        ],
        'code' => [
            'label' => $ll . ':tx_revealjseditor_codeblock.code',
            'config' => [
                // Mounts Monaco (the VS Code editor) in the BE form via our
                // custom renderType registered in ext_localconf.php. Same
                // Monaco instance the FE deck uses — one editor engine for
                // every code-display + code-edit surface this extension
                // owns. Implementation lives in
                // Classes/Form/Element/MonacoEditorElement.php and
                // Resources/Public/JavaScript/MonacoFormEngineElement.js.
                // `rows`/`cols` are ignored by Monaco itself (it owns its
                // own dimensions via the custom element's CSS) but kept
                // here so any future fallback rendering still has sane
                // defaults. The sibling `language` select column drives
                // Monaco's syntax mode — read in MonacoEditorElement::render()
                // from `$this->data['databaseRow']['language']`.
                'type' => 'text',
                'renderType' => 'monacoEditor',
                'rows' => 12,
                'cols' => 80,
            ],
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'headline, language, code,'
                . ' --div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visibility,'
                . ' hidden',
        ],
    ],
];
