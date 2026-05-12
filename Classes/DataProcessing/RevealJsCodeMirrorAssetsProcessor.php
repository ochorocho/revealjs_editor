<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\DataProcessing;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Registers the JavaScript modules our code slides need on the FE.
 *
 * Always loads:
 *   - `@typo3/backend/code-editor/element/code-mirror-element.js` — TYPO3's
 *     CodeMirror 6 wrapper. Triggers the ImportMap dependency resolver
 *     (`cms-core/Classes/Page/ImportMap.php:312-323`) which marks the whole
 *     `backend` package as required, so the composed importmap carries every
 *     `@typo3/backend/*` and `@codemirror/*` alias the element resolves at
 *     runtime. Slides without `<typo3-t3editor-codemirror>` markup simply
 *     don't instantiate it (the element only upgrades when present on the
 *     page), so the load is a one-time cost regardless of slide content.
 *
 * Conditionally (only when in visual_editor's edit mode):
 *   - `@ochorocho/revealjs-editor/RevealEditableCodemirror.js` — our small
 *     custom element that wraps each codemirror and pipes its native
 *     `change` event into visual_editor's dataHandlerStore so the BE save
 *     button persists the change. The gate replicates `EditModeService::isEditMode()`
 *     inline (`?editMode=1` AND $GLOBALS['BE_USER'] is a BackendUserAuthentication).
 *     We don't call the service directly because (a) it's marked `@internal`
 *     so the contract isn't load-bearing across visual_editor minors, and
 *     (b) it's registered as a private Symfony service, so neither
 *     `GeneralUtility::makeInstance()` (10 required ctor args) nor
 *     `Container::get()` (service has been removed/inlined at compile time)
 *     can reach it from a non-DI'd data processor. Inlining the two-line
 *     check is both safer and simpler. The BE-user check is the security
 *     boundary that prevents anonymous attackers from triggering the
 *     save-capable markup by appending `?editMode=1` to a public URL.
 *
 * Pure side-effect processor: returns the processedData array unmodified.
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
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule(
            '@typo3/backend/code-editor/element/code-mirror-element.js'
        );

        // In-edit-mode-only bridge to visual_editor's dataHandlerStore.
        // Inlines the two checks `EditModeService::isEditMode()` performs:
        // `?editMode=1` query param AND $GLOBALS['BE_USER'] is set. The
        // BE-user check is the security boundary that keeps anonymous
        // attackers from triggering save-capable markup by appending the
        // query param to a public URL.
        $request = $cObj->getRequest();
        $editModeRequested = $request !== null
            && ($request->getQueryParams()['editMode'] ?? false);
        $beUserAuthenticated = ($GLOBALS['BE_USER'] ?? null) instanceof BackendUserAuthentication;
        if ($editModeRequested && $beUserAuthenticated) {
            $pageRenderer->loadJavaScriptModule(
                '@ochorocho/revealjs-editor/RevealEditableCodemirror.js'
            );
        }

        return $processedData;
    }
}
