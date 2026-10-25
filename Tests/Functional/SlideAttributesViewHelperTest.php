<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Tests\Functional;

use Ochorocho\RevealJsEditor\ViewHelpers\SlideAttributesViewHelper;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Guards the presence-based boolean emission in SlideAttributesViewHelper.
 *
 * reveal.js reads data-background-video-loop (and -muted / -interactive) by
 * presence, ignoring the value — so the attribute must be emitted ONLY when the
 * checkbox is on, never as an inverted `="false"`. Regression cover for the
 * bg_video_loop inverted-logic bug.
 */
final class SlideAttributesViewHelperTest extends FunctionalTestCase
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
        'friendsoftypo3/visual-editor',
        'ochorocho/revealjs-editor',
    ];

    /**
     * @param array<string, mixed> $record
     */
    private function renderFor(array $record): string
    {
        $viewHelper = new SlideAttributesViewHelper();
        $viewHelper->setArguments(['record' => $record]);

        return $viewHelper->render();
    }

    public function testBackgroundVideoLoopEmittedOnlyWhenChecked(): void
    {
        // Checked → attribute present (reveal.js loops on presence).
        self::assertStringContainsString(
            'data-background-video-loop="true"',
            $this->renderFor(['uid' => 1, 'tx_revealjseditor_bg_video_loop' => 1]),
            'A checked "Loop background video" box must emit the attribute.',
        );

        // Unchecked → no attribute at all (must NOT emit the inverted `="false"`).
        self::assertStringNotContainsString(
            'data-background-video-loop',
            $this->renderFor(['uid' => 1, 'tx_revealjseditor_bg_video_loop' => 0]),
            'An unchecked "Loop background video" box must emit no attribute.',
        );
    }

    public function testBackgroundVideoMutedEmittedOnlyWhenChecked(): void
    {
        self::assertStringContainsString(
            'data-background-video-muted="true"',
            $this->renderFor(['uid' => 1, 'tx_revealjseditor_bg_video_muted' => 1]),
        );
        self::assertStringNotContainsString(
            'data-background-video-muted',
            $this->renderFor(['uid' => 1, 'tx_revealjseditor_bg_video_muted' => 0]),
        );
    }
}
