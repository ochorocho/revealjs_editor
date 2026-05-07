<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\DataProcessing;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Picks the correct theme CSS URL based on whether the page's selected theme
 * is one of the 14 reveal.js stock themes (shipped under
 * `Resources/Public/Vendor/revealjs/dist/theme/`) or a custom theme provided
 * by the extension under `Resources/Public/Styles/revealjs-themes/`.
 *
 * Without this processor, the page template was forced to emit BOTH paths
 * unconditionally — one always 404'd for any given theme value, generating
 * a `ResourceHashCollection` log entry per request.
 *
 * Output: a single `{themeUrl}` template variable (default `as`) that the
 * page template passes straight to `<f:asset.css href="{themeUrl}">`.
 */
final class RevealJsThemeProcessor implements DataProcessorInterface
{
    /**
     * The 14 themes shipped in reveal.js v6's `dist/theme/` folder.
     * Anything outside this list is treated as a local custom theme.
     */
    private const array STOCK_THEMES = [
        'black',
        'white',
        'beige',
        'blood',
        'dracula',
        'league',
        'moon',
        'night',
        'serif',
        'simple',
        'sky',
        'solarized',
        'black-contrast',
        'white-contrast',
    ];

    private const string STOCK_PATH = 'EXT:revealjs_editor/Resources/Public/Vendor/revealjs/dist/theme/';
    private const string CUSTOM_PATH = 'EXT:revealjs_editor/Resources/Public/Styles/revealjs-themes/';

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
        $theme = (string)($row['tx_revealjseditor_theme'] ?? 'black');
        if ($theme === '') {
            $theme = 'black';
        }

        $base = in_array($theme, self::STOCK_THEMES, true) ? self::STOCK_PATH : self::CUSTOM_PATH;

        $as = $processorConfiguration['as'] ?? 'themeUrl';
        $processedData[$as] = $base . $theme . '.css';

        return $processedData;
    }
}
