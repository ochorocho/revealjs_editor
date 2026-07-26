<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the raw rich-text speaker-notes value (tx_revealjseditor_slide_notes)
 * of a slide record, or an empty string when unset.
 *
 * Used by `Pages/Revealjs.html` to render the notes as an `<aside class="notes">`
 * child of each `<section>` — the only reveal.js notes form that carries HTML
 * (the data-notes attribute is plain-text only). The template wraps the return
 * value in `<f:format.html>` so the stored RTE markup is run through
 * `lib.parseFunc_RTE` (t3:// links resolved, entities decoded):
 *
 *   <f:variable name="slideNotes">{reveal:slideNotes(record: slide)}</f:variable>
 *   <f:if condition="{slideNotes}">
 *     <aside class="notes"><f:format.html>{slideNotes}</f:format.html></aside>
 *   </f:if>
 *
 * A dedicated ViewHelper (rather than `{slide.tx_revealjseditor_slide_notes}`)
 * is needed because a v14 Domain\Record exposes fields via get()/has() only and
 * is not reliably Fluid-dot-accessible; RecordFieldTrait::readField() handles
 * both the array and Record shapes the slide loop can hand us.
 */
final class SlideNotesViewHelper extends AbstractViewHelper
{
    use RecordFieldTrait;

    /**
     * Output is the raw stored RTE value; f:format.html in the template does
     * the parsing/escaping, so don't let Fluid escape it here.
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('record', 'mixed', 'The tt_content slide row (array or Domain\\Record)', true);
    }

    public function render(): string
    {
        $value = $this->readField($this->arguments['record'], 'tx_revealjseditor_slide_notes');

        return $value === null ? '' : (string)$value;
    }
}
