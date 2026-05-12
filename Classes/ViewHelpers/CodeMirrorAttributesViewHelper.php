<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\ViewHelpers;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Builds the HTML attribute string injected into the opening tag of a
 * `<typo3-t3editor-codemirror>` element so the FE CodeMirror view picks
 * up the right language mode for the given child row.
 *
 * Used by `Container/Code.html` per inline child:
 *
 *   <typo3-t3editor-codemirror{reveal:codeMirrorAttributes(language: block.data.language) -> f:format.raw()} readonly>
 *       <textarea>{block.data.code}</textarea>
 *   </typo3-t3editor-codemirror>
 *
 * Returns one of:
 *   - " mode='{...JSON...}'"        — leading space, ready to inject
 *   - ""                            — language unknown / unmapped
 *
 * The JSON shape matches what `Backend\Form\Element\CodeEditorElement`
 * emits for the same field in the BE (`JavaScriptModuleInstruction`
 * serialised via `GeneralUtility::jsonEncodeForHtmlAttribute(..., false)`).
 *
 * We deliberately *don't* go through TYPO3's `ModeRegistry` even though
 * it would resolve the same mapping. `ModeRegistry` and the `Mode`
 * value object are marked `@internal` in `cms-backend`, so binding to
 * them would couple this extension to internals that can change between
 * minors. The CodeMirror language modules themselves (`@codemirror/lang-*`)
 * are part of the BE asset bundle and are stable.
 *
 * Unmapped languages (`plaintext`, `yaml`, `bash`) deliberately yield
 * an empty attribute string. The element then renders monospace plain
 * text — matching the user-confirmed BE behaviour where the codeEditor
 * is format-agnostic (see memory/codeblock-format-agnostic.md).
 */
final class CodeMirrorAttributesViewHelper extends AbstractViewHelper
{
    /**
     * Output is raw HTML attribute markup; do not let Fluid double-escape.
     */
    protected $escapeOutput = false;

    /**
     * Language code → [`@codemirror/lang-*` specifier, exportName].
     *
     * `typescript` aliases to `@codemirror/lang-javascript` because
     * CodeMirror 6's javascript mode parses TS as well (passing
     * `{ typescript: true }` would require addons; the default tokens
     * are close enough for read-only display).
     */
    private const LANGUAGE_MODULES = [
        'php' => ['@codemirror/lang-php', 'php'],
        'javascript' => ['@codemirror/lang-javascript', 'javascript'],
        'typescript' => ['@codemirror/lang-javascript', 'javascript'],
        'html' => ['@codemirror/lang-html', 'html'],
        'css' => ['@codemirror/lang-css', 'css'],
        'json' => ['@codemirror/lang-json', 'json'],
        'xml' => ['@codemirror/lang-xml', 'xml'],
        'sql' => ['@codemirror/lang-sql', 'sql'],
        'typoscript' => ['@typo3/backend/code-editor/language/typoscript.js', 'typoscript'],
        // plaintext / yaml / bash → unmapped, intentionally
    ];

    public function initializeArguments(): void
    {
        $this->registerArgument('language', 'string', 'language code from tx_revealjseditor_codeblock.language', true);
    }

    public function render(): string
    {
        $language = (string)$this->arguments['language'];
        if (!isset(self::LANGUAGE_MODULES[$language])) {
            return '';
        }

        [$specifier, $exportName] = self::LANGUAGE_MODULES[$language];
        // `->invoke()` adds an ITEM_INVOKE entry to the instruction's items
        // array, telling `executeJavaScriptModuleInstruction()` to actually
        // call the named export (e.g. `html()`) and return its result —
        // which is the CodeMirror Extension the editor needs. Without
        // `invoke()` the dispatcher loads the module and returns `[]`, so
        // CodeMirror would init with no language extension and the editor
        // would never finish rendering content. Mirrors how the BE wires
        // each mode in `cms-backend/Configuration/Backend/T3editor/Modes.php`.
        $instruction = JavaScriptModuleInstruction::create($specifier, $exportName)->invoke();

        // Same encoder CodeEditorElement uses for the BE form's mode
        // attribute (cms-backend/Classes/Form/Element/CodeEditorElement.php).
        // The `false` second arg keeps the JSON un-quoted by the encoder so
        // we can place it ourselves inside single quotes — single-quoted
        // attributes don't need the JSON's double quotes escaped.
        $modeJson = GeneralUtility::jsonEncodeForHtmlAttribute($instruction, false);

        return ' mode=\'' . $modeJson . '\'';
    }
}
