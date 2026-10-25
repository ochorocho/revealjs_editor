<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Tests\Functional;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * EXT:visual_editor is optional (composer `suggest`). Without it,
 * `reveal:editMode` must never report edit mode, so code blocks stay
 * read-only and the visual_editor JS module is never imported.
 */
final class EditModeViewHelperTest extends FunctionalTestCase
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

    public function testIsNotEditModeWithoutVisualEditorEvenForBackendUser(): void
    {
        $GLOBALS['BE_USER'] = new BackendUserAuthentication();
        $request = (new ServerRequest('https://example.com/?editMode=1'))
            ->withQueryParams(['editMode' => '1']);

        self::assertSame('0', $this->render($request));
    }

    public function testIsNotEditModeWithoutEditModeParameter(): void
    {
        self::assertSame('0', $this->render(new ServerRequest('https://example.com/')));
    }

    private function render(ServerRequest $request): string
    {
        $context = $this->get(RenderingContextFactory::class)->create([], $request);
        $context->getTemplatePaths()->setTemplateSource('{reveal:editMode(then: \'1\', else: \'0\')}');

        return (string)(new TemplateView($context))->render();
    }
}
