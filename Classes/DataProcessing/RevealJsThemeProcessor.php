<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\DataProcessing;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Resolves the page's selected theme to its CSS URL.
 *
 * The bundled `the-caemp` theme lives under the extension's
 * `Resources/Public/Styles/revealjs-themes/` folder and is the default (an
 * empty value falls back to it too). Other extensions — e.g. a sitepackage —
 * can register their own themes without patching this extension by mapping a
 * theme value to a CSS path in
 * `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['revealjs_editor']['themes']`
 * (typically from their `ext_localconf.php`); the registry is consulted first
 * and wins over the bundled folder.
 *
 * Output: a `{themeUrl}` template variable (default `as`) that the page template
 * passes to `<f:asset.css href="{themeUrl}">`, plus a fixed `{themeLogo}` variable
 * (the theme's default corner-logo path) — used as the last fallback in the
 * `.reveal-logo` chain: per-page FAL logo → `revealjs.defaultLogo` setting (if
 * non-empty) → `{themeLogo}`.
 */
final class RevealJsThemeProcessor implements DataProcessorInterface
{
    private const string DEFAULT_THEME = 'the-caemp';
    private const string THEME_PATH = 'EXT:revealjs_editor/Resources/Public/Styles/revealjs-themes/';

    /**
     * Per-theme default corner logo. Falls back to the DEFAULT_THEME entry for
     * unknown themes; overridable per theme via
     * `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['revealjs_editor']['themeLogos']`.
     */
    private const array THEME_LOGOS = [
        'the-caemp' => 'EXT:revealjs_editor/Resources/Public/Images/the-camp.png',
        'typo3' => 'EXT:revealjs_editor/Resources/Public/Images/typo3-logo.svg',
        'b13' => 'EXT:revealjs_editor/Resources/Public/Images/b13-logo.svg',
    ];

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
        $processedData[$as] = $this->resolveThemeUrl($theme);
        $processedData['themeLogo'] = $this->resolveThemeLogo($theme);

        return $processedData;
    }

    /**
     * Registered themes (value => CSS path) win over the bundled folder,
     * letting external extensions ship their own themes.
     */
    private function resolveThemeUrl(string $theme): string
    {
        $registry = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['revealjs_editor']['themes'] ?? [];
        if (is_array($registry) && is_string($registry[$theme] ?? null) && $registry[$theme] !== '') {
            return $registry[$theme];
        }

        return self::THEME_PATH . $theme . '.css';
    }

    /**
     * Registered theme logos (value => image path) win over the bundled map,
     * so an externally-registered theme can ship its own default logo.
     */
    private function resolveThemeLogo(string $theme): string
    {
        $registry = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['revealjs_editor']['themeLogos'] ?? [];
        if (is_array($registry) && is_string($registry[$theme] ?? null) && $registry[$theme] !== '') {
            return $registry[$theme];
        }

        return self::THEME_LOGOS[$theme] ?? self::THEME_LOGOS[self::DEFAULT_THEME];
    }
}
