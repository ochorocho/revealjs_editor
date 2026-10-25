<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

/**
 * Renders the then-child when the request is visual_editor's edit mode,
 * otherwise the else-child.
 *
 * Replacement for visual_editor's `f:editMode()` so EXT:visual_editor stays
 * optional: without it the deck is always read-only. Mirrors
 * visual_editor's EditModeService::isEditMode() (`editMode` query param +
 * logged-in BE user) — that service is private in its DI container, so it
 * can't be resolved from here.
 *
 *   data-editable="{reveal:editMode(then: '1', else: '0')}"
 */
final class EditModeViewHelper extends AbstractConditionViewHelper
{
    public function render(): mixed
    {
        $request = $this->renderingContext?->getAttribute(ServerRequestInterface::class);

        if ($request instanceof ServerRequestInterface && self::isEditMode($request)) {
            return $this->renderThenChild();
        }

        return $this->renderElseChild();
    }

    private static function isEditMode(ServerRequestInterface $request): bool
    {
        return ExtensionManagementUtility::isLoaded('visual_editor')
            && isset($request->getQueryParams()['editMode'])
            && ($GLOBALS['BE_USER'] ?? null) instanceof BackendUserAuthentication;
    }
}
