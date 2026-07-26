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
        $processedData[$as] = $this->resolveThemeUrl($theme);

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
}
