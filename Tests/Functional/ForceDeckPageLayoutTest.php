<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Tests\Functional;

use Ochorocho\RevealJsEditor\EventListener\ForceDeckPageLayout;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Event\AfterPageAndLanguageIsResolvedEvent;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Issue #1: a Reveal Presentation page always uses the `revealjs` page layout,
 * whatever backend layout is stored on it or inherited from its rootline.
 */
final class ForceDeckPageLayoutTest extends FunctionalTestCase
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
     * @param array<string, mixed> $pageRecord
     */
    private function dispatchFor(array $pageRecord): PageInformation
    {
        $pageInformation = new PageInformation();
        $pageInformation->setId((int)$pageRecord['uid']);
        $pageInformation->setPageRecord($pageRecord);
        $pageInformation->setRootLine([$pageRecord]);

        $event = new AfterPageAndLanguageIsResolvedEvent(new ServerRequest('https://example.com/'), $pageInformation);
        $this->get(ForceDeckPageLayout::class)($event);

        return $event->getPageInformation();
    }

    public function testDeckPageGetsRevealjsLayoutInsteadOfAForeignOne(): void
    {
        $pageInformation = $this->dispatchFor(['uid' => 12, 'doktype' => 1731, 'backend_layout' => 'pagets__CaminoContentpage']);

        self::assertSame('pagets__revealjs', $pageInformation->getPageRecord()['backend_layout']);
    }

    public function testDeckPageWithEmptyLayoutGetsRevealjsLayout(): void
    {
        $pageInformation = $this->dispatchFor(['uid' => 12, 'doktype' => 1731, 'backend_layout' => '']);

        self::assertSame('pagets__revealjs', $pageInformation->getPageRecord()['backend_layout']);
    }

    public function testOtherPagesKeepTheirLayout(): void
    {
        $pageInformation = $this->dispatchFor(['uid' => 2, 'doktype' => 1, 'backend_layout' => 'pagets__CaminoContentpage']);

        self::assertSame('pagets__CaminoContentpage', $pageInformation->getPageRecord()['backend_layout']);
    }

    public function testBackendLayoutFieldsAreReadOnlyOnDeckPages(): void
    {
        $overrides = $GLOBALS['TCA']['pages']['types']['1731']['columnsOverrides'] ?? [];

        self::assertTrue($overrides['backend_layout']['config']['readOnly'] ?? false);
        self::assertTrue($overrides['backend_layout_next_level']['config']['readOnly'] ?? false);
    }
}
