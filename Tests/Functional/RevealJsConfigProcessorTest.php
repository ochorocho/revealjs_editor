<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Tests\Functional;

use Ochorocho\RevealJsEditor\DataProcessing\RevealJsConfigProcessor;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Security regression: the reveal.js config JSON is embedded in the
 * single-quoted `data-revealjs-options='…'` HTML attribute, so no
 * editor-controlled string value may contain a character able to break out of
 * the attribute (or close a <script> block). The processor must encode
 * ' " < > & as \uXXXX (JSON_HEX_* flags).
 */
final class RevealJsConfigProcessorTest extends FunctionalTestCase
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

    public function testConfigJsonCannotBreakOutOfTheHtmlAttribute(): void
    {
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        // autoAnimateEasing is a free-text (TCA type=input) page field whose
        // value lands in the JSON as a string — the XSS breakout vector.
        $cObj->data = [
            'tx_revealjseditor_autoanimateeasing' => "ease' onmouseover='alert(1)</script><x&y",
        ];

        $result = (new RevealJsConfigProcessor())->process($cObj, [], [], []);
        $json = $result['revealjsConfig'] ?? null;

        self::assertIsString($json);
        // The attribute is single-quoted, so a literal ' would break out; < > &
        // would allow </script> / markup injection. None may appear literally.
        self::assertStringNotContainsString("'", $json, 'Single quote must be \\u0027-escaped.');
        self::assertStringNotContainsString('<', $json, 'Less-than must be \\u003C-escaped.');
        self::assertStringNotContainsString('>', $json, 'Greater-than must be \\u003E-escaped.');
        self::assertStringNotContainsString('&', $json, 'Ampersand must be \\u0026-escaped.');
        // And the payload is preserved (encoded), not stripped.
        self::assertStringContainsString('\\u0027', $json, 'The apostrophe must survive as \\u0027.');
    }

    public function testProcessorDoesNotVaryOnRequestParameters(): void
    {
        // Regression for the editMode cache-poisoning fix: the config is a pure
        // function of the page record and must not read request data, so two
        // runs with the same row produce identical output regardless of URL.
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $cObj->data = ['tx_revealjseditor_keyboard' => 1];

        $first = (new RevealJsConfigProcessor())->process($cObj, [], [], []);
        $second = (new RevealJsConfigProcessor())->process($cObj, [], [], []);

        self::assertSame($first['revealjsConfig'], $second['revealjsConfig']);
        // keyboard reflects the record, never a forced request-based override.
        self::assertStringContainsString('"keyboard":true', $first['revealjsConfig']);
    }
}
