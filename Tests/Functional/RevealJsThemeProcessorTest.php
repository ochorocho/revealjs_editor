<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Tests\Functional;

use Ochorocho\RevealJsEditor\DataProcessing\RevealJsThemeProcessor;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Asserts RevealJsThemeProcessor resolves both the theme CSS ({themeUrl}) and
 * the per-theme default corner logo ({themeLogo}) from the page's
 * tx_revealjseditor_theme value.
 */
final class RevealJsThemeProcessorTest extends FunctionalTestCase
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function runProcessor(array $row): array
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObj->data = $row;

        return (new RevealJsThemeProcessor())->process($cObj, [], [], []);
    }

    public function testThemeUrlAndLogoResolvePerTheme(): void
    {
        $caemp = $this->runProcessor(['tx_revealjseditor_theme' => 'the-caemp']);
        self::assertStringEndsWith('revealjs-themes/the-caemp.css', $caemp['themeUrl']);
        self::assertStringEndsWith('Images/the-camp.png', $caemp['themeLogo']);

        $typo3 = $this->runProcessor(['tx_revealjseditor_theme' => 'typo3']);
        self::assertStringEndsWith('revealjs-themes/typo3.css', $typo3['themeUrl']);
        self::assertStringEndsWith('Images/typo3-logo.svg', $typo3['themeLogo']);
    }

    public function testThemeLogoFallsBackToDefaultThemeForEmptyOrUnknown(): void
    {
        // Empty theme value → DEFAULT_THEME (the-caemp).
        $empty = $this->runProcessor(['tx_revealjseditor_theme' => '']);
        self::assertStringEndsWith('Images/the-camp.png', $empty['themeLogo']);

        // Unknown theme value → the-caemp logo (map fallback), not a broken path.
        $unknown = $this->runProcessor(['tx_revealjseditor_theme' => 'does-not-exist']);
        self::assertStringEndsWith('Images/the-camp.png', $unknown['themeLogo']);
    }
}
