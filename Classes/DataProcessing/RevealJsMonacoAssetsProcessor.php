<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\DataProcessing;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Loads the JavaScript module that mounts Monaco (the VS Code editor)
 * on every doktype-1731 page — for anonymous viewers (read-only display)
 * AND for visual_editor's preview iframe (editable + dataHandlerStore
 * wiring). The same RevealMonacoMount.js handles both; the template
 * stamps `data-editable="1"` onto blocks rendered in edit mode to flip
 * the per-editor read-only/editable bit.
 *
 * No edit-mode gating here: Monaco is the single editor across all
 * surfaces, so the bundle loads unconditionally. The dataHandlerStore
 * import inside the mount module is dynamic (lazy `import()`), so the
 * visual_editor bundle is only fetched when an editor actually flips a
 * Monaco instance into edit mode — anonymous viewers never pull it.
 *
 * Pure side-effect processor: returns the processedData array unmodified.
 */
final class RevealJsMonacoAssetsProcessor implements DataProcessorInterface
{
    /**
     * Data processors are instantiated via `GeneralUtility::makeInstance()`
     * by `ContentDataProcessor::instantiateDataProcessor()` (cms-frontend/
     * Classes/ContentObject/ContentDataProcessor.php) with NO constructor
     * arguments — Symfony DI doesn't fill required-typed parameters in that
     * path. The other processors in this extension (`RevealJsConfigProcessor`,
     * `RevealJsThemeProcessor`) sidestep this by not having ctor params; we
     * do the same and pull `PageRenderer` via the service container inside
     * `process()`.
     *
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
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule(
            '@ochorocho/revealjs-editor/RevealMonacoMount.js'
        );

        return $processedData;
    }
}
