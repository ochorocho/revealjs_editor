<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\DataProcessing;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Resolves the page's selected theme to its CSS URL under the extension's
 * `Resources/Public/Styles/revealjs-themes/` folder. Only the `the-caemp`
 * theme ships today; the field defaults to it and an empty value falls back
 * to it too.
 *
 * Output: a single `{themeUrl}` template variable (default `as`) that the
 * page template passes straight to `<f:asset.css href="{themeUrl}">`.
 */
final class RevealJsThemeProcessor implements DataProcessorInterface
{
    private const string DEFAULT_THEME = 'the-caemp';
    private const string THEME_PATH = 'EXT:revealjs_editor/Resources/Public/Styles/revealjs-themes/';

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $row = $cObj->data;
        $theme = (string)($row['tx_revealjseditor_theme'] ?? self::DEFAULT_THEME);
        if ($theme === '') {
            $theme = self::DEFAULT_THEME;
        }

        $as = $processorConfiguration['as'] ?? 'themeUrl';
        $processedData[$as] = self::THEME_PATH . $theme . '.css';

        return $processedData;
    }
}
