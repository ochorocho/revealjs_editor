<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FormEngine renderType `monacoEditor` — mounts the VS Code editor
 * (Monaco) inside the TYPO3 BE form. Used by `tx_revealjseditor_codeblock.code`
 * via `'renderType' => 'monacoEditor'` in its TCA config.
 *
 * Registered in ext_localconf.php under
 * `$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry']`.
 *
 * Pattern follows TYPO3 core's `Build/vendor/typo3/cms-backend/Classes/Form/
 * Element/CodeEditorElement.php` — same `AbstractFormElement` extension
 * point, same `resultArray` shape, same field-wizard wiring. The only
 * differences are:
 *   - emits `<typo3-monaco-editor>` (defined in
 *     Resources/Public/JavaScript/MonacoFormEngineElement.js) instead of
 *     `<typo3-t3editor-codemirror>`
 *   - skips the addon / keymap / mode registry plumbing (that's all
 *     CodeMirror-specific; Monaco owns its own editor configuration in
 *     the JS side)
 *
 * The mounted Monaco editor reads/writes its value through a hidden
 * `<textarea>` that the FormEngine save POST handler picks up via the
 * standard `data[<table>][<uid>][<field>]` POST naming — no custom
 * DataHandler glue needed.
 */
class MonacoEditorElement extends AbstractFormElement
{
    /**
     * Default field wizards enabled for this element. Same set as
     * CodeEditorElement so the field looks consistent with the rest of
     * FormEngine: localisation indicators + diff against the default
     * language. Reordering / additions should mirror what TYPO3 core
     * does on its own AbstractFormElement subclasses.
     *
     * @var array<string, array<string, mixed>>
     */
    protected $defaultFieldWizard = [
        'localizationStateSelector' => [
            'renderType' => 'localizationStateSelector',
        ],
        'otherLanguageContent' => [
            'renderType' => 'otherLanguageContent',
            'after' => [
                'localizationStateSelector',
            ],
        ],
        'defaultLanguageDifferences' => [
            'renderType' => 'defaultLanguageDifferences',
            'after' => [
                'otherLanguageContent',
            ],
        ],
    ];

    /**
     * @return array<string, mixed> As defined in initializeResultArray() of AbstractNode
     */
    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@ochorocho/revealjs-editor/MonacoFormEngineElement.js'
        );

        $parameterArray = $this->data['parameterArray'];

        // FormEngine field naming — `name` is what the BE save POST handler
        // looks for to put the value back into DataHandler. Pulled from the
        // standard `itemFormElName` slot.
        $name = $parameterArray['itemFormElName'];
        $value = (string)($parameterArray['itemFormElValue'] ?? '');

        // The language for syntax highlighting comes from a sibling column
        // on the same inline-IRRE row — the `language` select dropdown
        // (TCA config: `tx_revealjseditor_codeblock.language`). We read it
        // from the database row TYPO3 hands us in `$this->data['databaseRow']`.
        // Fall back to 'plaintext' if the row hasn't been saved yet or the
        // value isn't in the expected shape.
        $language = 'plaintext';
        $rowLanguage = $this->data['databaseRow']['language'] ?? null;
        if (is_string($rowLanguage) && $rowLanguage !== '') {
            $language = $rowLanguage;
        } elseif (is_array($rowLanguage) && isset($rowLanguage[0]) && is_string($rowLanguage[0])) {
            // Some FormEngine paths wrap select values in arrays.
            $language = $rowLanguage[0];
        }

        // Render the standard FormEngine field-information / -control / -wizard
        // bands around the editor so it integrates with the rest of the form
        // (localisation indicators, the diff-against-default-language overlay,
        // etc.).
        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $fieldControlResult = $this->renderFieldControl();
        $fieldControlHtml = $fieldControlResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $fieldWizardHtml = $fieldWizardResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        // The textarea is the form's POST carrier — it MUST have the
        // `name` attribute FormEngine expects so the save handler picks
        // up the value. We want it invisible (the custom element overlays
        // Monaco on top; the JS module writes the editor's value back
        // into this textarea on every change), but FormEngine's own
        // stylesheet rules can override the bare `hidden` attribute on
        // textareas — adding an inline `display: none` wins via
        // specificity. The browser still POSTs the value of named form
        // controls regardless of CSS visibility, so save-back is
        // unaffected.
        $textareaAttributes = [
            'name' => $name,
            'id' => 'monaco_' . md5($name),
            'hidden' => '',
            'style' => 'display: none;',
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($parameterArray['fieldConf']['config']),
        ];

        $editorMarkup = [];
        $editorMarkup[] = '<typo3-monaco-editor language="' . htmlspecialchars($language, ENT_QUOTES) . '">';
        $editorMarkup[] = GeneralUtility::renderTextarea($value, $textareaAttributes);
        $editorMarkup[] = '</typo3-monaco-editor>';
        $editorHtml = implode("\n", $editorMarkup);

        $html = [];
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $html[] =   $fieldInformationHtml;
        $html[] =   '<div class="form-control-wrap">';
        $html[] =       '<div class="form-wizards-wrap">';
        $html[] =           '<div class="form-wizards-item-element">';
        $html[] =               $editorHtml;
        $html[] =           '</div>';
        if (!empty($fieldControlHtml)) {
            $html[] =           '<div class="form-wizards-item-aside form-wizards-item-aside--field-control">';
            $html[] =               '<div class="btn-group">';
            $html[] =                   $fieldControlHtml;
            $html[] =               '</div>';
            $html[] =           '</div>';
        }
        if (!empty($fieldWizardHtml)) {
            $html[] = '<div class="form-wizards-item-bottom">';
            $html[] = $fieldWizardHtml;
            $html[] = '</div>';
        }
        $html[] =       '</div>';
        $html[] =   '</div>';
        $html[] = '</div>';

        $resultArray['html'] = $this->wrapWithFieldsetAndLegend(implode("\n", $html));

        return $resultArray;
    }
}
