<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\Form\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * FormEngine renderType `mermaidEditor` — a split-pane live diagram editor for
 * the `tx_revealjseditor_mermaid` column: a Monaco text editor plus a live
 * mermaid.js preview that re-renders as the editor types.
 *
 * Registered in ext_localconf.php under
 * `$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry']`.
 *
 * Mirrors MonacoEditorElement (same AbstractFormElement pattern, same hidden
 * `<textarea>` POST carrier, same field-wizard wiring). Differences: emits
 * `<typo3-mermaid-editor>` (defined in
 * Resources/Public/JavaScript/MermaidFormEngineElement.js) and carries no
 * `language` attribute — the diagram source is mermaid syntax, edited as
 * plaintext.
 */
class MermaidEditorElement extends AbstractFormElement
{
    /**
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
            '@ochorocho/revealjs-editor/MermaidFormEngineElement.js'
        );

        $parameterArray = $this->data['parameterArray'];
        $name = $parameterArray['itemFormElName'];
        $value = (string)($parameterArray['itemFormElValue'] ?? '');

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $fieldControlResult = $this->renderFieldControl();
        $fieldControlHtml = $fieldControlResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $fieldWizardHtml = $fieldWizardResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        // The textarea is the FormEngine POST carrier (its `name` is what the
        // save handler reads); the custom element mounts Monaco over it and
        // writes the value back on every change. Hidden via inline style so
        // FormEngine's own textarea CSS can't reveal it; value is still POSTed.
        $textareaAttributes = [
            'name' => $name,
            'id' => 'mermaid_' . md5($name),
            'hidden' => '',
            'style' => 'display: none;',
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($parameterArray['fieldConf']['config']),
        ];

        $editorMarkup = [];
        $editorMarkup[] = '<typo3-mermaid-editor>';
        $editorMarkup[] = GeneralUtility::renderTextarea($value, $textareaAttributes);
        $editorMarkup[] = '</typo3-mermaid-editor>';
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
