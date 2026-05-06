<?php

declare(strict_types=1);

defined('TYPO3') or die();

use B13\Container\Tca\ContainerConfiguration;
use B13\Container\Tca\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

(static function (): void {
    $ll = 'LLL:EXT:revealjs_editor/Resources/Private/Language/locallang_db.xlf';
    $iconBase = 'EXT:revealjs_editor/Resources/Public/Icons/';

    // ----- Per-slide TCA columns ---------------------------------------------------
    // Declared globally; surfaced only via columnsOverrides on each container CType
    // below, so they remain invisible on every other tt_content row.

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

    // ----- Container CType registration --------------------------------------------

    $registry = GeneralUtility::makeInstance(Registry::class);
    \assert($registry instanceof Registry);

    /** @var list<array{cType: string, label: string, description: string, grid: list<list<array<string, mixed>>>, icon: string}> $slideTypes */
    $slideTypes = [
        [
            'cType' => 'revealjs_slide_single',
            'label' => $ll . ':ctype.revealjs_slide_single',
            'description' => $ll . ':ctype.revealjs_slide_single.description',
            'grid' => [
                [['name' => 'Body', 'colPos' => 200]],
            ],
            'icon' => 'content-revealjs-slide-single',
        ],
        [
            'cType' => 'revealjs_slide_two_column',
            'label' => $ll . ':ctype.revealjs_slide_two_column',
            'description' => $ll . ':ctype.revealjs_slide_two_column.description',
            'grid' => [
                [
                    ['name' => 'Left', 'colPos' => 210],
                    ['name' => 'Right', 'colPos' => 211],
                ],
            ],
            'icon' => 'content-revealjs-slide-two-column',
        ],
        [
            'cType' => 'revealjs_slide_title_content',
            'label' => $ll . ':ctype.revealjs_slide_title_content',
            'description' => $ll . ':ctype.revealjs_slide_title_content.description',
            'grid' => [
                [['name' => 'Title', 'colPos' => 220]],
                [['name' => 'Body', 'colPos' => 221]],
            ],
            'icon' => 'content-revealjs-slide-title-content',
        ],
        [
            'cType' => 'revealjs_slide_image_left',
            'label' => $ll . ':ctype.revealjs_slide_image_left',
            'description' => $ll . ':ctype.revealjs_slide_image_left.description',
            'grid' => [
                [
                    ['name' => 'Image', 'colPos' => 230],
                    ['name' => 'Text', 'colPos' => 231],
                ],
            ],
            'icon' => 'content-revealjs-slide-image-left',
        ],
    ];

    foreach ($slideTypes as $slideType) {
        $registry->configureContainer(
            (new ContainerConfiguration(
                $slideType['cType'],
                $slideType['label'],
                $slideType['description'],
                $slideType['grid'],
            ))->setIcon($slideType['icon'])
        );
    }

    // ----- Per-CType: surface slide meta fields + bind RTE preset ------------------
    // Container's Registry has already populated $GLOBALS['TCA']['tt_content']['types'][$cType]['showitem']
    // with the container's own form. We append a dedicated "Slide" tab with our two
    // fields, and route any bodytext on this CType through the revealjs RTE preset.

    foreach ($slideTypes as $slideType) {
        $cType = $slideType['cType'];
        $existingShowItem = $GLOBALS['TCA']['tt_content']['types'][$cType]['showitem'] ?? '';
        $GLOBALS['TCA']['tt_content']['types'][$cType]['showitem'] = $existingShowItem
            . ',--div--;' . $ll . ':tt_content.tab.slide,'
            . 'tx_revealjseditor_transition, tx_revealjseditor_data_state';

        $GLOBALS['TCA']['tt_content']['types'][$cType]['columnsOverrides']['bodytext']['config']['richtextConfiguration']
            = 'revealjs';
    }
})();
