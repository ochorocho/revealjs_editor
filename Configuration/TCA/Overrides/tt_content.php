<?php

declare(strict_types=1);

defined('TYPO3') or die();

(static function (): void {
    $ll = 'LLL:EXT:revealjs_editor/Resources/Private/Language/locallang_db.xlf';

    $GLOBALS['TCA']['tt_content']['columns']['tx_revealjseditor_transition'] = [
        'label' => $ll . ':tt_content.transition',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'default' => 'slide',
            'items' => [
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

    // ----- Register the revealjs_slide_cover CType --------------------------------

    $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'][] = [
        'label' => $ll . ':ctype.revealjs_slide_cover',
        'value' => 'revealjs_slide_cover',
        'icon' => 'content-revealjs-slide-cover',
        'group' => 'default',
    ];

    // ----- Slide-meta tab on the CTypes that can appear on a reveal.js page -------
    $slideTab = ',--div--;' . $ll . ':tt_content.tab.slide,'
        . 'tx_revealjseditor_transition, tx_revealjseditor_data_state';

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
        . $slideTab;

    foreach (['text', 'textmedia'] as $cType) {
        $existing = $GLOBALS['TCA']['tt_content']['types'][$cType]['showitem'] ?? '';
        $GLOBALS['TCA']['tt_content']['types'][$cType]['showitem'] = $existing . $slideTab;
    }

    $GLOBALS['TCA']['tt_content']['types']['revealjs_slide_cover']['columnsOverrides']['bodytext']['config']['enableRichtext'] = true;
    // $GLOBALS['TCA']['tt_content']['types']['revealjs_slide_cover']['columnsOverrides']['bodytext']['config']['richtextConfiguration'] = 'revealjs';
})();
