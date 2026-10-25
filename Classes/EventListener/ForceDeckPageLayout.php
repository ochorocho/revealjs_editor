<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageLayoutResolver;
use TYPO3\CMS\Frontend\Event\AfterPageAndLanguageIsResolvedEvent;

/**
 * Hardcodes the `revealjs` page layout for Reveal Presentation pages.
 *
 * PAGEVIEW picks its template by the page's backend layout name and fills
 * {content} from that layout's content areas. A deck page with any other
 * layout — set on the page, inherited via a parent's
 * backend_layout_next_level, or "none" — would look for a missing template
 * and lose its slides. Overriding both the page record and the resolved
 * page layout makes every deck render Pages/Revealjs.html with colPos 0 as
 * {content.main}.
 */
#[AsEventListener('revealjs-editor/force-deck-page-layout')]
final readonly class ForceDeckPageLayout
{
    public const int DOKTYPE = 1731;
    public const string PAGE_LAYOUT = 'pagets__revealjs';

    public function __construct(
        private PageLayoutResolver $pageLayoutResolver,
    ) {}

    public function __invoke(AfterPageAndLanguageIsResolvedEvent $event): void
    {
        $pageInformation = $event->getPageInformation();
        $pageRecord = $pageInformation->getPageRecord();
        if ((int)($pageRecord['doktype'] ?? 0) !== self::DOKTYPE
            || ($pageRecord['backend_layout'] ?? '') === self::PAGE_LAYOUT
        ) {
            return;
        }

        $pageRecord['backend_layout'] = self::PAGE_LAYOUT;
        $pageInformation->setPageRecord($pageRecord);

        $pageLayout = $this->pageLayoutResolver->getLayoutForPage($pageRecord, $pageInformation->getRootLine());
        if ($pageLayout !== null) {
            $pageInformation->setPageLayout($pageLayout);
        }
    }
}
