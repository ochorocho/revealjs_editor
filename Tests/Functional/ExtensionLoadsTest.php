<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Smoke test asserting the structural pieces of the extension are wired up:
 * - the doktype 1731 subschema is registered with allowedRecordTypes,
 * - the four slide CTypes are present in tt_content,
 * - the per-slide TCA columns and the RTE preset key are set.
 */
final class ExtensionLoadsTest extends FunctionalTestCase
{
    /**
     * @var list<non-empty-string>
     */
    protected array $coreExtensionsToLoad = [
        'fluid_styled_content',
        'info',
        'rte_ckeditor',
        'seo',
    ];

    /**
     * @var list<non-empty-string>
     */
    protected array $testExtensionsToLoad = [
        'ochorocho/revealjs-editor',
    ];

    public function testRevealJsDoktypeSubschemaIsRegistered(): void
    {
        $type = $GLOBALS['TCA']['pages']['types'][1731] ?? null;

        self::assertIsArray($type, 'Doktype 1731 must be registered as a TCA subschema on pages.');
        self::assertSame(['*'], $type['allowedRecordTypes'] ?? null);

        // The Reveal Presentation tab references a palette named reveal_appearance,
        // which carries the theme + logo fields.
        self::assertStringContainsString(
            'reveal_appearance',
            $type['showitem'] ?? '',
            'showitem of doktype 1731 must include the reveal_appearance palette.',
        );
        self::assertStringContainsString(
            'tx_revealjseditor_theme',
            $GLOBALS['TCA']['pages']['palettes']['reveal_appearance']['showitem'] ?? '',
            'reveal_appearance palette must contain the theme select.',
        );
    }

    public function testDoktypeOptionIsAddedToPagesDoktypeSelect(): void
    {
        $items = $GLOBALS['TCA']['pages']['columns']['doktype']['config']['items'] ?? [];
        $values = array_column($items, 'value');

        self::assertContains(1731, $values, 'Doktype 1731 must be selectable in pages.doktype.');
    }

    public function testThemeColumnIsRegisteredWithBundledThemes(): void
    {
        $config = $GLOBALS['TCA']['pages']['columns']['tx_revealjseditor_theme']['config'] ?? null;

        self::assertIsArray($config, 'tx_revealjseditor_theme column must be registered on pages.');
        self::assertSame('select', $config['type'] ?? null);
        self::assertSame('the-caemp', $config['default'] ?? null);

        // Two bundled themes ship: the-caemp (default) and typo3.
        $values = array_column($config['items'] ?? [], 'value');
        self::assertSame(['the-caemp', 'typo3'], $values, 'the-caemp and typo3 must be the bundled themes.');
    }

    public function testSpeakerViewCacheHashParamsAreWhitelisted(): void
    {
        $excluded = $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] ?? [];

        // ext_localconf.php must whitelist visual_editor's editMode param and
        // the reveal.js Notes speaker-view params, otherwise TYPO3's strict
        // cacheHash validation 404s those client-built iframe/preview URLs.
        foreach (['editMode', 'receiver', 'postMessageEvents', 'controls', 'scrollActivationWidth'] as $param) {
            self::assertContains(
                $param,
                $excluded,
                sprintf('Query param "%s" must be cHash-excluded for the speaker view / BE preview.', $param),
            );
        }
    }

    public function testBrandColorSettingsAreDefined(): void
    {
        $file = dirname(__DIR__, 2) . '/Configuration/Sets/RevealjsEditor/settings.definitions.yaml';
        self::assertFileExists($file);

        $parsed = \Symfony\Component\Yaml\Yaml::parseFile($file);

        // Each brand color is an OPTIONAL override: `string` type with an empty
        // default (a `color`-type setting can't be empty, and empty is our
        // "use the active theme's own --cmp-* default" signal).
        foreach (['background', 'text', 'accent', 'muted'] as $color) {
            $key = 'revealjs.color.' . $color;
            self::assertArrayHasKey($key, $parsed['settings'] ?? [], sprintf('Setting "%s" must be declared.', $key));
            self::assertSame('string', $parsed['settings'][$key]['type'] ?? null, sprintf('Setting "%s" must be type=string.', $key));
            self::assertSame('', $parsed['settings'][$key]['default'] ?? null, sprintf('Setting "%s" must default to empty (theme fallback).', $key));
        }
    }

    /**
     * @return list<array{0: non-empty-string}>
     */
    public static function slideCTypesProvider(): array
    {
        return [
            ['revealjs_slide_cover'],
            ['revealjs_slide_mermaid'],
            ['text'],
            ['textmedia'],
        ];
    }

    #[DataProvider('slideCTypesProvider')]
    public function testSlideCTypeIsRegistered(string $cType): void
    {
        $items = $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] ?? [];
        $values = array_column($items, 'value');

        self::assertContains($cType, $values, sprintf('CType "%s" must be registered.', $cType));
    }

    #[DataProvider('slideCTypesProvider')]
    public function testSlideCTypeShowsPerSlideMetaFields(string $cType): void
    {
        $showitem = $GLOBALS['TCA']['tt_content']['types'][$cType]['showitem'] ?? '';

        // The slide-meta fields live in palettes referenced from the showitem.
        self::assertStringContainsString('tt_content.tab.slide', $showitem);
        self::assertStringContainsString('revealjs_slide', $showitem);

        $slidePalette = $GLOBALS['TCA']['tt_content']['palettes']['revealjs_slide']['showitem'] ?? '';
        self::assertStringContainsString('tx_revealjseditor_transition', $slidePalette);
        self::assertStringContainsString('tx_revealjseditor_data_state', $slidePalette);
    }

    public function testRevealjsRtePresetIsRegistered(): void
    {
        self::assertSame(
            'EXT:revealjs_editor/Configuration/RTE/Revealjs.yaml',
            $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['revealjs'] ?? null,
        );
    }

    public function testCoverCTypeBindsRevealjsRtePresetOnBodytext(): void
    {
        $config = $GLOBALS['TCA']['tt_content']['types']['revealjs_slide_cover']['columnsOverrides']['bodytext']['config']
            ?? [];

        self::assertTrue(
            (bool)($config['enableRichtext'] ?? false),
            'Cover CType bodytext must opt into the rich-text editor.',
        );
        self::assertSame(
            'revealjs',
            $config['richtextConfiguration'] ?? null,
            'Cover CType bodytext must use the trimmed revealjs RTE preset.',
        );
    }

    public function testPerSlideTcaColumnsAreRegistered(): void
    {
        self::assertArrayHasKey(
            'tx_revealjseditor_transition',
            $GLOBALS['TCA']['tt_content']['columns'] ?? [],
        );
        self::assertArrayHasKey(
            'tx_revealjseditor_data_state',
            $GLOBALS['TCA']['tt_content']['columns'] ?? [],
        );

        $transitionConfig = $GLOBALS['TCA']['tt_content']['columns']['tx_revealjseditor_transition']['config'] ?? null;
        self::assertSame('select', $transitionConfig['type'] ?? null);
        // 6 reveal.js transitions plus an "Inherit from presentation" entry that
        // suppresses emission of data-transition so the page-level config wins.
        self::assertCount(7, $transitionConfig['items'] ?? [], 'Inherit + six reveal.js transitions must be selectable.');
    }
}
