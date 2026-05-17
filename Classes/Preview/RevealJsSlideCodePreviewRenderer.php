<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Preview;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Page-module preview renderer for the `revealjs_slide_code` CType.
 *
 * Extends the standard preview (which shows the slide's header + bodytext
 * lede the same way `text`'s default preview does) by appending a list
 * of the slide's child code-block headlines — one `<li>` per inline-IRRE
 * row in `tx_revealjseditor_codeblock`, ordered by `sorting`. Empty
 * `headline` values render as the TYPO3-standard "[No title]" label
 * (`LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.no_title`,
 * matching how core's BackendUtility::getRecordTitle() handles empty
 * record titles).
 *
 * Registered in Configuration/TCA/Overrides/tt_content.php under
 * `$GLOBALS['TCA']['tt_content']['types']['revealjs_slide_code']['previewRenderer']`.
 * TYPO3's `StandardPreviewRendererResolver` resolves the per-type slot
 * before the table-level default, so this renderer wins for code slides
 * and the parent class's standard preview applies to every other CType.
 */
class RevealJsSlideCodePreviewRenderer extends StandardContentPreviewRenderer
{
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $standard = parent::renderPageModulePreviewContent($item);

        $parentUid = (int)($item->getRecord()->getRawRecord()?->toArray()['uid'] ?? 0);
        if ($parentUid === 0) {
            return $standard;
        }

        $rows = $this->fetchCodeblocks($parentUid);
        if ($rows === []) {
            return $standard;
        }

        // The parent's protected getLanguageService() returns $GLOBALS['LANG'].
        // Same LLL key TYPO3 core uses (BackendUtility::getRecordTitle()
        // falls back to it for label-less records).
        $noTitle = $GLOBALS['LANG']->sL(
            'LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.no_title'
        );

        $listItems = '';
        foreach ($rows as $row) {
            $headline = trim((string)($row['headline'] ?? ''));
            $listItems .= $headline !== ''
                ? '<li>' . htmlspecialchars($headline) . '</li>'
                : '<li><em>' . htmlspecialchars($noTitle) . '</em></li>';
        }

        return $standard . '<ul class="list-unstyled">' . $listItems . '</ul>';
    }

    /**
     * Pull the child rows directly via QueryBuilder rather than going
     * through the FE DatabaseQueryProcessor used at render time. Reasons:
     *   - Page-module previews run in the BE context where the FE
     *     ContentObjectRenderer isn't available.
     *   - We only need two cheap columns (uid, headline) ordered by
     *     sorting; a one-shot QueryBuilder is leaner than instantiating
     *     FE rendering infrastructure.
     *   - The child table is auto-schema'd (no SQL file) and lives in
     *     tx_revealjseditor_codeblock — TCA at Configuration/TCA/
     *     tx_revealjseditor_codeblock.php.
     *
     * @return list<array{uid: int, headline: string|null}>
     */
    private function fetchCodeblocks(int $parentUid): array
    {
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_revealjseditor_codeblock');
        // DefaultRestrictionContainer (applied automatically) already
        // hides deleted + disabled rows the same way the FE pipeline does.
        /** @var list<array{uid: int, headline: string|null}> $rows */
        $rows = $qb->select('uid', 'headline')
            ->from('tx_revealjseditor_codeblock')
            ->where(
                $qb->expr()->eq(
                    'parent_uid',
                    $qb->createNamedParameter($parentUid, ParameterType::INTEGER)
                )
            )
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
        return $rows;
    }
}
