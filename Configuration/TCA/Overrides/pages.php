<?php

declare(strict_types=1);

defined('TYPO3') or die();

(static function (): void {
    $doktype = 1731;

    // Add doktype option to the pages.doktype select dropdown
    $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'][] = [
        'label' => 'LLL:EXT:revealjs_editor/Resources/Private/Language/locallang_db.xlf:pages.doktype.revealjs',
        'value' => $doktype,
        'icon' => 'apps-pagetree-revealjs',
        'group' => 'default',
    ];

    // Theme select column on pages — only shown for our doktype via the showitem below
    $GLOBALS['TCA']['pages']['columns']['tx_revealjseditor_theme'] = [
        'label' => 'LLL:EXT:revealjs_editor/Resources/Private/Language/locallang_db.xlf:pages.theme',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => 'black',
            'items' => [
                ['label' => 'THE CÄMP', 'value' => 'the-caemp'],
                ['label' => 'Black', 'value' => 'black'],
                ['label' => 'White', 'value' => 'white'],
                ['label' => 'Beige', 'value' => 'beige'],
                ['label' => 'Blood', 'value' => 'blood'],
                ['label' => 'Dracula', 'value' => 'dracula'],
                ['label' => 'League', 'value' => 'league'],
                ['label' => 'Moon', 'value' => 'moon'],
                ['label' => 'Night', 'value' => 'night'],
                ['label' => 'Serif', 'value' => 'serif'],
                ['label' => 'Simple', 'value' => 'simple'],
                ['label' => 'Sky', 'value' => 'sky'],
                ['label' => 'Solarized', 'value' => 'solarized'],
                ['label' => 'Black (high contrast)', 'value' => 'black-contrast'],
                ['label' => 'White (high contrast)', 'value' => 'white-contrast'],
            ],
        ],
    ];

    // Doktype subschema (v14 replacement for PageDoktypeRegistry::add()).
    // Mirrors core's pattern at typo3/cms-core/Configuration/TCA/pages.php (lines 689, 880).
    // The columnsOverrides default the BackendLayout (and the layout for sub-pages)
    // to our `revealjs` layout, which PAGEVIEW resolves to Pages/Revealjs.html and
    // exposes its single column as {content.main} for f:render.contentArea.
    $GLOBALS['TCA']['pages']['types'][(string)$doktype] = [
        'allowedRecordTypes' => ['*'],
        'showitem' => '
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                --palette--;;standard,
                --palette--;;title,
                tx_revealjseditor_theme,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:metadata,
                --palette--;;abstract,
                --palette--;;metatags,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:appearance,
                --palette--;;layout,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:behaviour,
                --palette--;;config,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                --palette--;;visibility,
                --palette--;;access,
        ',
        'columnsOverrides' => [
            'backend_layout' => [
                'config' => ['default' => 'pagets__revealjs'],
            ],
            'backend_layout_next_level' => [
                'config' => ['default' => 'pagets__revealjs'],
            ],
        ],
    ];

    // Page tree / record-listing icon.
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'][(string)$doktype] = 'apps-pagetree-revealjs';
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'][$doktype . '-hideinmenu'] = 'apps-pagetree-revealjs';
    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'][$doktype . '-root'] = 'apps-pagetree-revealjs';
})();
