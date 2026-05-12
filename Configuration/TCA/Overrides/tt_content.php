<?php

declare(strict_types=1);

defined('TYPO3') or die();

(static function (): void {
    $ll = 'LLL:EXT:revealjs_editor/Resources/Private/Language/locallang_db.xlf';

    // Per slide fields
    $GLOBALS['TCA']['tt_content']['columns']['tx_revealjseditor_transition'] = [
        'label' => $ll . ':tt_content.transition',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => '',
            'items' => [
                ['label' => $ll . ':tt_content.transition.inherit', 'value' => ''],
                ['label' => $ll . ':tt_content.transition.none', 'value' => 'none'],
                ['label' => $ll . ':tt_content.transition.fade', 'value' => 'fade'],
                ['label' => $ll . ':tt_content.transition.slide', 'value' => 'slide'],
                ['label' => $ll . ':tt_content.transition.convex', 'value' => 'convex'],
                ['label' => $ll . ':tt_content.transition.concave', 'value' => 'concave'],
                ['label' => $ll . ':tt_content.transition.zoom', 'value' => 'zoom'],
            ],
        ],
    ];

    $GLOBALS['TCA']['tt_content']['columns']['tx_revealjseditor_data_state'] = [
        'label' => $ll . ':tt_content.data_state',
        'description' => $ll . ':tt_content.data_state.description',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim',
        ],
    ];

    // Independent CSS class on the slide section. Decoupled from data-state
    // so editors can target reveal.js's "viewport class on active slide"
    // (data-state) and "section class always present" (class) separately.
    $GLOBALS['TCA']['tt_content']['columns']['tx_revealjseditor_class'] = [
        'label' => $ll . ':tt_content.section_class',
        'description' => $ll . ':tt_content.section_class.description',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'eval' => 'trim',
        ],
    ];

    // ----- Per-slide reveal.js options ----------------------------------------------
    // Single source of truth shared with Classes/ViewHelpers/SlideAttributesViewHelper.php.
    // Each entry generates one TCA column and is read at render time by the ViewHelper
    // to emit the matching data-* attribute on the wrapping <section>.
    $slideOptions = [
        // Backgrounds
        'transition_speed' => ['type' => 'select', 'default' => '', 'items' => [
            ['label' => $ll . ':tt_content.bg.inherit', 'value' => ''],
            ['label' => 'Default', 'value' => 'default'],
            ['label' => 'Fast', 'value' => 'fast'],
            ['label' => 'Slow', 'value' => 'slow'],
        ]],
        'bg_color' => ['type' => 'input', 'default' => '', 'size' => 20],
        'bg_gradient' => ['type' => 'input', 'default' => '', 'size' => 60],
        'bg_image' => ['type' => 'file', 'default' => 0, 'allowed' => 'common-image-types'],
        'bg_video' => ['type' => 'file', 'default' => 0, 'allowed' => 'mp4,webm,ogv,ogg,mov,m4v'],
        'bg_iframe' => ['type' => 'input', 'default' => '', 'size' => 60],
        'bg_size' => ['type' => 'input', 'default' => '', 'size' => 20],
        'bg_position' => ['type' => 'input', 'default' => '', 'size' => 20],
        'bg_repeat' => ['type' => 'select', 'default' => '', 'items' => [
            ['label' => $ll . ':tt_content.bg.inherit', 'value' => ''],
            ['label' => 'no-repeat', 'value' => 'no-repeat'],
            ['label' => 'repeat', 'value' => 'repeat'],
            ['label' => 'repeat-x', 'value' => 'repeat-x'],
            ['label' => 'repeat-y', 'value' => 'repeat-y'],
        ]],
        'bg_opacity' => ['type' => 'input', 'default' => '', 'size' => 6],
        'bg_transition' => ['type' => 'select', 'default' => '', 'items' => [
            ['label' => $ll . ':tt_content.bg.inherit', 'value' => ''],
            ['label' => $ll . ':tt_content.transition.none', 'value' => 'none'],
            ['label' => $ll . ':tt_content.transition.fade', 'value' => 'fade'],
            ['label' => $ll . ':tt_content.transition.slide', 'value' => 'slide'],
            ['label' => $ll . ':tt_content.transition.convex', 'value' => 'convex'],
            ['label' => $ll . ':tt_content.transition.concave', 'value' => 'concave'],
            ['label' => $ll . ':tt_content.transition.zoom', 'value' => 'zoom'],
        ]],
        'bg_video_loop' => ['type' => 'check', 'default' => 1],
        'bg_video_muted' => ['type' => 'check', 'default' => 0],
        'bg_interactive' => ['type' => 'check', 'default' => 0],

        // Slide behaviour
        'slide_autoslide' => ['type' => 'number', 'default' => 0],
        'slide_visibility' => ['type' => 'select', 'default' => '', 'items' => [
            ['label' => $ll . ':tt_content.slide.visibility.visible', 'value' => ''],
            ['label' => $ll . ':tt_content.slide.visibility.hidden', 'value' => 'hidden'],
            ['label' => $ll . ':tt_content.slide.visibility.uncounted', 'value' => 'uncounted'],
        ]],
        'slide_prevent_swipe' => ['type' => 'check', 'default' => 0],
        'slide_notes' => ['type' => 'text', 'default' => '', 'rows' => 4, 'cols' => 60],

        // Auto-animate
        'anim_enabled' => ['type' => 'check', 'default' => 0],
        'anim_id' => ['type' => 'input', 'default' => '', 'size' => 30],
        'anim_restart' => ['type' => 'check', 'default' => 0],
        'anim_easing' => ['type' => 'input', 'default' => '', 'size' => 20],
        'anim_duration' => ['type' => 'input', 'default' => '', 'size' => 6],
        'anim_unmatched' => ['type' => 'check', 'default' => 1],
    ];

    foreach ($slideOptions as $field => $spec) {
        $columnName = 'tx_revealjseditor_' . $field;
        $label = $ll . ':tt_content.slide.' . $field;

        $config = match ($spec['type']) {
            'check' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => (int)$spec['default'],
                'items' => [['label' => '', 'invertStateDisplay' => false]],
            ],
            'input' => [
                'type' => 'input',
                'size' => $spec['size'],
                'default' => (string)$spec['default'],
                'eval' => 'trim',
            ],
            'number' => [
                'type' => 'number',
                'size' => 10,
                'default' => (int)$spec['default'],
            ],
            'text' => [
                'type' => 'text',
                'rows' => $spec['rows'],
                'cols' => $spec['cols'],
                'default' => (string)$spec['default'],
            ],
            'select' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => $spec['default'],
                'items' => $spec['items'],
            ],
            'file' => [
                'type' => 'file',
                'maxitems' => 1,
                'allowed' => $spec['allowed'],
            ],
        };

        $GLOBALS['TCA']['tt_content']['columns'][$columnName] = [
            'label' => $label,
            'config' => $config,
        ];
    }

    // ----- Register the revealjs_slide_cover CType --------------------------------

    $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'][] = [
        'label' => $ll . ':ctype.revealjs_slide_cover',
        'value' => 'revealjs_slide_cover',
        'icon' => 'content-revealjs-slide-cover',
        'group' => 'default',
    ];

    // ----- Palettes for the per-slide tabs -----------------------------------------
    // Three tabs: Slide (transition / behaviour) / Background / Animation.
    $GLOBALS['TCA']['tt_content']['palettes']['revealjs_slide'] = [
        'showitem' => 'tx_revealjseditor_transition,tx_revealjseditor_transition_speed,--linebreak--,'
            . 'tx_revealjseditor_data_state,tx_revealjseditor_class,tx_revealjseditor_slide_visibility,--linebreak--,'
            . 'tx_revealjseditor_slide_autoslide,tx_revealjseditor_slide_prevent_swipe,--linebreak--,'
            . 'tx_revealjseditor_slide_notes',
    ];
    $GLOBALS['TCA']['tt_content']['palettes']['revealjs_background'] = [
        'showitem' => 'tx_revealjseditor_bg_color,tx_revealjseditor_bg_opacity,tx_revealjseditor_bg_transition,--linebreak--,'
            . 'tx_revealjseditor_bg_gradient,--linebreak--,'
            . 'tx_revealjseditor_bg_image,tx_revealjseditor_bg_size,tx_revealjseditor_bg_position,tx_revealjseditor_bg_repeat,--linebreak--,'
            . 'tx_revealjseditor_bg_video,tx_revealjseditor_bg_video_loop,tx_revealjseditor_bg_video_muted,--linebreak--,'
            . 'tx_revealjseditor_bg_iframe,tx_revealjseditor_bg_interactive',
    ];
    $GLOBALS['TCA']['tt_content']['palettes']['revealjs_animate'] = [
        'showitem' => 'tx_revealjseditor_anim_enabled,tx_revealjseditor_anim_restart,tx_revealjseditor_anim_unmatched,--linebreak--,'
            . 'tx_revealjseditor_anim_id,tx_revealjseditor_anim_easing,tx_revealjseditor_anim_duration',
    ];

    $slideTabs = ',--div--;' . $ll . ':tt_content.tab.slide,'
        . '--palette--;;revealjs_slide'
        . ',--div--;' . $ll . ':tt_content.tab.background,'
        . '--palette--;;revealjs_background'
        . ',--div--;' . $ll . ':tt_content.tab.animate,'
        . '--palette--;;revealjs_animate';

    // The cover CType also gets a Media tab with the standard fluid_styled_content
    // image field + cropping / gallery / link palettes (declared globally on
    // tt_content by fluid_styled_content). FilesProcessor in setup.typoscript
    // exposes those references as {files} for Cover.html.
    $coverMediaTab = ',--div--;core.form.tabs:media,'
        . 'image,'
        . '--palette--;;mediaAdjustments,'
        . '--palette--;;gallerySettings,'
        . '--palette--;;imagelinks';

    $GLOBALS['TCA']['tt_content']['types']['revealjs_slide_cover']['showitem'] =
        ($GLOBALS['TCA']['tt_content']['types']['text']['showitem'] ?? '')
        . $coverMediaTab
        . $slideTabs;

    foreach (['text', 'textmedia'] as $cType) {
        $existing = $GLOBALS['TCA']['tt_content']['types'][$cType]['showitem'] ?? '';
        $GLOBALS['TCA']['tt_content']['types'][$cType]['showitem'] = $existing . $slideTabs;
    }

    // RTE on cover bodytext, scoped to the trimmed `revealjs` preset
    // (Configuration/RTE/Revealjs.yaml — registered globally in ext_localconf.php).
    // Pattern mirrors core's sys_news.php (cms-core/Configuration/TCA/sys_news.php:40).
    $GLOBALS['TCA']['tt_content']['types']['revealjs_slide_cover']['columnsOverrides']['bodytext']['config']['enableRichtext'] = true;
    $GLOBALS['TCA']['tt_content']['types']['revealjs_slide_cover']['columnsOverrides']['bodytext']['config']['richtextConfiguration'] = 'revealjs';

    // ----- Register the revealjs_slide_code CType ---------------------------------
    // A slide that stacks multiple inline code blocks. Each child carries its
    // own headline + language + code; the parent gives the slide an h1 title
    // (inherits text's showitem). FE rendering goes through Code.html and
    // reveal.js's bundled highlight plugin.

    $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'][] = [
        'label' => $ll . ':ctype.revealjs_slide_code',
        'value' => 'revealjs_slide_code',
        'icon' => 'content-revealjs-slide-code',
        'group' => 'default',
    ];

    // The inline-collection field. Child rows live in tx_revealjseditor_codeblock
    // (TCA file: Configuration/TCA/tx_revealjseditor_codeblock.php). The schema
    // analyser creates that table from its TCA columns block; no SQL needed.
    $GLOBALS['TCA']['tt_content']['columns']['tx_revealjseditor_codeblocks'] = [
        'label' => $ll . ':tt_content.codeblocks',
        'config' => [
            'type' => 'inline',
            'foreign_table' => 'tx_revealjseditor_codeblock',
            'foreign_field' => 'parent_uid',
            'foreign_sortby' => 'sorting',
            'appearance' => [
                'collapseAll' => false,
                'levelLinksPosition' => 'top',
                'showSynchronizationLink' => true,
                'showAllLocalizationLink' => true,
                'showPossibleLocalizationRecords' => true,
                'useSortable' => true,
                'enabledControls' => [
                    'info' => true,
                    'new' => true,
                    'sort' => true,
                    'dragdrop' => true,
                    'hide' => true,
                    'delete' => true,
                    'localize' => true,
                ],
            ],
        ],
    ];

    // Code-slide showitem: inherit text (gives header + bodytext for the
    // slide title / optional lede), add the Code Blocks tab with the inline
    // collection, then the three slide-options tabs every reveal CType shares.
    $GLOBALS['TCA']['tt_content']['types']['revealjs_slide_code']['showitem'] =
        ($GLOBALS['TCA']['tt_content']['types']['text']['showitem'] ?? '')
        . ',--div--;' . $ll . ':tt_content.tab.codeblocks,'
        . 'tx_revealjseditor_codeblocks'
        . $slideTabs;
})();
