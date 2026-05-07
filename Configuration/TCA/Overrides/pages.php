<?php

declare(strict_types=1);

defined('TYPO3') or die();

(static function (): void {
    $doktype = 1731;
    $ll = 'LLL:EXT:revealjs_editor/Resources/Private/Language/locallang_db.xlf';

    // Add doktype option to the pages.doktype select dropdown
    $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'][] = [
        'label' => $ll . ':pages.doktype.revealjs',
        'value' => $doktype,
        'icon' => 'apps-pagetree-revealjs',
        'group' => 'default',
    ];

    // -----------------------------------------------------------------
    // Theme — moved into the new Reveal Presentation tab below.
    // -----------------------------------------------------------------
    $GLOBALS['TCA']['pages']['columns']['tx_revealjseditor_theme'] = [
        'label' => $ll . ':pages.theme',
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

    // Per-presentation logo (FAL single-image). Falls back to the site setting
    // `revealjs.defaultLogo` when not set — handled in Pages/Revealjs.html.
    $GLOBALS['TCA']['pages']['columns']['tx_revealjseditor_logo'] = [
        'label' => $ll . ':pages.logo',
        'description' => $ll . ':pages.logo.description',
        'config' => [
            'type' => 'file',
            'maxitems' => 1,
            'allowed' => 'common-image-types',
        ],
    ];

    // -----------------------------------------------------------------
    // Reveal.js options — single source of truth for TCA + JSON config.
    // The same key set is consumed by
    // Classes/DataProcessing/RevealJsConfigProcessor.php to build the
    // data-revealjs-options JSON at FE render time.
    //
    // Spec format:
    //   field name (without `tx_revealjseditor_` prefix)
    //     => ['type' => 'check'|'input'|'number'|'select'|'select-decimal',
    //         'default' => …,
    //         'items'   => […]    // for selects
    //        ]
    // -----------------------------------------------------------------
    $revealOptions = [
        // Navigation & UI
        'controls' => ['type' => 'check', 'default' => 1],
        'controlstutorial' => ['type' => 'check', 'default' => 1],
        'controlslayout' => ['type' => 'select', 'default' => 'bottom-right', 'items' => [
            ['label' => 'Bottom right', 'value' => 'bottom-right'],
            ['label' => 'Edges', 'value' => 'edges'],
        ]],
        'controlsbackarrows' => ['type' => 'select', 'default' => 'faded', 'items' => [
            ['label' => 'Faded', 'value' => 'faded'],
            ['label' => 'Hidden', 'value' => 'hidden'],
            ['label' => 'Visible', 'value' => 'visible'],
        ]],
        'progress' => ['type' => 'check', 'default' => 1],
        'slidenumber' => ['type' => 'select', 'default' => 'false', 'items' => [
            ['label' => 'Off', 'value' => 'false'],
            ['label' => 'Horizontal . Vertical (h.v)', 'value' => 'h.v'],
            ['label' => 'Horizontal / Vertical (h/v)', 'value' => 'h/v'],
            ['label' => 'Current (c)', 'value' => 'c'],
            ['label' => 'Current / Total (c/t)', 'value' => 'c/t'],
        ]],
        'showslidenumber' => ['type' => 'select', 'default' => 'all', 'items' => [
            ['label' => 'All contexts', 'value' => 'all'],
            ['label' => 'Print only', 'value' => 'print'],
            ['label' => 'Speaker view only', 'value' => 'speaker'],
        ]],
        'keyboard' => ['type' => 'check', 'default' => 1],
        'overview' => ['type' => 'check', 'default' => 1],
        'touch' => ['type' => 'check', 'default' => 1],
        'mousewheel' => ['type' => 'check', 'default' => 0],
        'navigationmode' => ['type' => 'select', 'default' => 'default', 'items' => [
            ['label' => 'Default', 'value' => 'default'],
            ['label' => 'Linear (no vertical)', 'value' => 'linear'],
            ['label' => 'Grid (jump per axis)', 'value' => 'grid'],
        ]],
        'embedded' => ['type' => 'check', 'default' => 0],
        'help' => ['type' => 'check', 'default' => 1],
        'pause' => ['type' => 'check', 'default' => 1],
        'previewlinks' => ['type' => 'check', 'default' => 0],

        // URL / history
        'hash' => ['type' => 'check', 'default' => 0],
        'hashonebasedindex' => ['type' => 'check', 'default' => 0],
        'history' => ['type' => 'check', 'default' => 0],
        'fragmentinurl' => ['type' => 'check', 'default' => 1],

        // Layout
        'center' => ['type' => 'check', 'default' => 1],
        'viewdistance' => ['type' => 'number', 'default' => 3],

        // Auto-advance
        'autoslide' => ['type' => 'number', 'default' => 0],
        'autoslidestoppable' => ['type' => 'check', 'default' => 1],

        // Transitions
        'transition' => ['type' => 'select', 'default' => 'slide', 'items' => [
            ['label' => 'None', 'value' => 'none'],
            ['label' => 'Fade', 'value' => 'fade'],
            ['label' => 'Slide', 'value' => 'slide'],
            ['label' => 'Convex', 'value' => 'convex'],
            ['label' => 'Concave', 'value' => 'concave'],
            ['label' => 'Zoom', 'value' => 'zoom'],
        ]],
        'transitionspeed' => ['type' => 'select', 'default' => 'default', 'items' => [
            ['label' => 'Default', 'value' => 'default'],
            ['label' => 'Fast', 'value' => 'fast'],
            ['label' => 'Slow', 'value' => 'slow'],
        ]],
        'backgroundtransition' => ['type' => 'select', 'default' => 'fade', 'items' => [
            ['label' => 'None', 'value' => 'none'],
            ['label' => 'Fade', 'value' => 'fade'],
            ['label' => 'Slide', 'value' => 'slide'],
            ['label' => 'Convex', 'value' => 'convex'],
            ['label' => 'Concave', 'value' => 'concave'],
            ['label' => 'Zoom', 'value' => 'zoom'],
        ]],

        // Fragments / animations
        'fragments' => ['type' => 'check', 'default' => 1],
        'autoanimate' => ['type' => 'check', 'default' => 1],
        'autoanimateeasing' => ['type' => 'input', 'default' => 'ease'],
        'autoanimateduration' => ['type' => 'input', 'default' => '1.0'],
        'autoanimateunmatched' => ['type' => 'check', 'default' => 1],

        // Loop / direction
        'loop' => ['type' => 'check', 'default' => 0],
        'rtl' => ['type' => 'check', 'default' => 0],
        'shuffle' => ['type' => 'check', 'default' => 0],

        // Speaker / print
        'shownotes' => ['type' => 'check', 'default' => 0],
        'pdfmaxpagesperslide' => ['type' => 'number', 'default' => 0],
        'pdfseparatefragments' => ['type' => 'check', 'default' => 1],

        // Media
        'autoplaymedia' => ['type' => 'select', 'default' => 'default', 'items' => [
            ['label' => 'Default (browser autoplay rules)', 'value' => 'default'],
            ['label' => 'Always autoplay', 'value' => 'always'],
            ['label' => 'Never autoplay', 'value' => 'never'],
        ]],
        'preloadiframes' => ['type' => 'select', 'default' => 'default', 'items' => [
            ['label' => 'Default (when slide adjacent)', 'value' => 'default'],
            ['label' => 'Always preload', 'value' => 'always'],
            ['label' => 'Never preload', 'value' => 'never'],
        ]],

        // Scroll mode (reveal.js 5+)
        'view' => ['type' => 'select', 'default' => 'default', 'items' => [
            ['label' => 'Default (one slide at a time)', 'value' => 'default'],
            ['label' => 'Scroll (vertical scroll layout)', 'value' => 'scroll'],
        ]],
        'scrolllayout' => ['type' => 'select', 'default' => 'full', 'items' => [
            ['label' => 'Full', 'value' => 'full'],
            ['label' => 'Compact', 'value' => 'compact'],
        ]],
        'scrollsnap' => ['type' => 'select', 'default' => 'mandatory', 'items' => [
            ['label' => 'Mandatory', 'value' => 'mandatory'],
            ['label' => 'Proximity', 'value' => 'proximity'],
            ['label' => 'None', 'value' => 'none'],
        ]],
        'scrollprogress' => ['type' => 'select', 'default' => 'auto', 'items' => [
            ['label' => 'Auto', 'value' => 'auto'],
            ['label' => 'Always show', 'value' => 'on'],
            ['label' => 'Hide', 'value' => 'off'],
        ]],
        'scrollactivationwidth' => ['type' => 'number', 'default' => 435],
    ];

    // Generate the TCA columns from the spec above.
    foreach ($revealOptions as $field => $spec) {
        $columnName = 'tx_revealjseditor_' . $field;
        $label = $ll . ':pages.reveal.' . $field;

        $config = match ($spec['type']) {
            'check' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => (int)$spec['default'],
                'items' => [['label' => '', 'invertStateDisplay' => false]],
            ],
            'input' => [
                'type' => 'input',
                'size' => 20,
                'default' => (string)$spec['default'],
                'eval' => 'trim',
            ],
            'number' => [
                'type' => 'number',
                'size' => 10,
                'default' => (int)$spec['default'],
            ],
            'select' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => $spec['default'],
                'items' => $spec['items'],
            ],
        };

        $GLOBALS['TCA']['pages']['columns'][$columnName] = [
            'label' => $label,
            'config' => $config,
        ];
    }

    // -----------------------------------------------------------------
    // Palettes for grouping the reveal.js options inside the tab.
    // -----------------------------------------------------------------
    $palettes = [
        'reveal_appearance' => 'tx_revealjseditor_theme,tx_revealjseditor_logo',
        'reveal_navigation' => 'tx_revealjseditor_controls,tx_revealjseditor_controlstutorial,'
            . 'tx_revealjseditor_controlslayout,tx_revealjseditor_controlsbackarrows,--linebreak--,'
            . 'tx_revealjseditor_progress,tx_revealjseditor_slidenumber,tx_revealjseditor_showslidenumber,--linebreak--,'
            . 'tx_revealjseditor_keyboard,tx_revealjseditor_overview,tx_revealjseditor_touch,tx_revealjseditor_mousewheel,--linebreak--,'
            . 'tx_revealjseditor_navigationmode,tx_revealjseditor_embedded,tx_revealjseditor_help,'
            . 'tx_revealjseditor_pause,tx_revealjseditor_previewlinks',
        'reveal_url' => 'tx_revealjseditor_hash,tx_revealjseditor_hashonebasedindex,'
            . 'tx_revealjseditor_history,tx_revealjseditor_fragmentinurl',
        'reveal_layout' => 'tx_revealjseditor_center,tx_revealjseditor_viewdistance',
        'reveal_autoadvance' => 'tx_revealjseditor_autoslide,tx_revealjseditor_autoslidestoppable',
        'reveal_transitions' => 'tx_revealjseditor_transition,tx_revealjseditor_transitionspeed,'
            . 'tx_revealjseditor_backgroundtransition',
        'reveal_fragments' => 'tx_revealjseditor_fragments,tx_revealjseditor_autoanimate,--linebreak--,'
            . 'tx_revealjseditor_autoanimateeasing,tx_revealjseditor_autoanimateduration,'
            . 'tx_revealjseditor_autoanimateunmatched',
        'reveal_loop' => 'tx_revealjseditor_loop,tx_revealjseditor_rtl,tx_revealjseditor_shuffle',
        'reveal_print' => 'tx_revealjseditor_shownotes,tx_revealjseditor_pdfmaxpagesperslide,'
            . 'tx_revealjseditor_pdfseparatefragments',
        'reveal_media' => 'tx_revealjseditor_autoplaymedia,tx_revealjseditor_preloadiframes',
        'reveal_scroll' => 'tx_revealjseditor_view,tx_revealjseditor_scrolllayout,'
            . 'tx_revealjseditor_scrollsnap,tx_revealjseditor_scrollprogress,'
            . 'tx_revealjseditor_scrollactivationwidth',
    ];
    foreach ($palettes as $key => $showitem) {
        $GLOBALS['TCA']['pages']['palettes'][$key] = [
            'label' => $ll . ':palette.' . $key,
            'showitem' => $showitem,
        ];
    }

    // Doktype subschema — Reveal Presentation tab grouped via palettes.
    $GLOBALS['TCA']['pages']['types'][(string)$doktype] = [
        'allowedRecordTypes' => ['*'],
        'showitem' => '
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                --palette--;;standard,
                --palette--;;title,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:metadata,
                --palette--;;abstract,
                --palette--;;metatags,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:appearance,
                --palette--;;layout,
            --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:behaviour,
                --palette--;;config,
            --div--;' . $ll . ':tab.reveal,
                --palette--;;reveal_appearance,
                --palette--;;reveal_transitions,
                --palette--;;reveal_navigation,
                --palette--;;reveal_layout,
                --palette--;;reveal_autoadvance,
                --palette--;;reveal_fragments,
                --palette--;;reveal_loop,
                --palette--;;reveal_url,
                --palette--;;reveal_media,
                --palette--;;reveal_scroll,
                --palette--;;reveal_print,
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
