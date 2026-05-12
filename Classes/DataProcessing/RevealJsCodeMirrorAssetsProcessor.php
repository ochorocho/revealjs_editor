<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\DataProcessing;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Registers TYPO3's `<typo3-t3editor-codemirror>` web component as a
 * JavaScript module on the current FE request. This is the only thing
 * the processor does — it returns the processedData array unmodified.
 *
 * Why a dedicated processor instead of e.g. a Fluid ViewHelper or a
 * TypoScript `includeJSModule` directive: this is a one-line PHP call
 * (`$pageRenderer->loadJavaScriptModule(...)`) that hooks cleanly into
 * the existing page-level `dataProcessing` chain in setup.typoscript,
 * and it's trivially grep-able when someone wonders "where does the
 * FE pull in CodeMirror from?".
 *
 * Mechanism: `loadJavaScriptModule()` triggers the ImportMap dependency
 * resolver (`cms-core/Classes/Page/ImportMap.php:312-323`) which marks
 * the whole `backend` package as required. The composed `<script
 * type="importmap">` then carries every `@typo3/backend/*` and
 * `@codemirror/*` alias the element resolves at runtime — so dynamic
 * mode imports like `@codemirror/lang-php` work out of the box.
 *
 * The element renders client-side from the importmap entry; no SSR.
 * Slides without `<typo3-t3editor-codemirror>` markup simply don't
 * instantiate it — CodeMirror only runs when there's an element to
 * upgrade. Unconditional load on every doktype-1731 page is the
 * trade-off for not querying tt_content to detect code slides first.
 */
final class RevealJsCodeMirrorAssetsProcessor implements DataProcessorInterface
{
    /**
     * Note: data processors are instantiated via `GeneralUtility::makeInstance()`
     * by `ContentDataProcessor::instantiateDataProcessor()` (cms-frontend/Classes/
     * ContentObject/ContentDataProcessor.php:99) with NO constructor arguments —
     * Symfony DI doesn't fill required-typed parameters in that path. The other
     * processors in this extension (`RevealJsConfigProcessor`, `RevealJsThemeProcessor`)
     * sidestep this by not having ctor params. We do the same and pull
     * `PageRenderer` via the service container inside `process()`.
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
        GeneralUtility::makeInstance(PageRenderer::class)->loadJavaScriptModule(
            '@typo3/backend/code-editor/element/code-mirror-element.js'
        );

        return $processedData;
    }
}
