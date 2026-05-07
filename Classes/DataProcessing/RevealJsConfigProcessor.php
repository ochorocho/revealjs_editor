<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\DataProcessing;

use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Builds the reveal.js JSON configuration object from the current page
 * record's tx_revealjseditor_* fields and assigns it as a single Fluid
 * variable (default name: `revealjsConfig`) for emission as the
 * <div class="reveal" data-revealjs-options="…"> attribute.
 *
 * Reveal.js's Reveal.initialize() accepts the same keys as defaults if
 * omitted, so emitting the editor's chosen values for *every* known
 * option is harmless and keeps editor intent explicit.
 *
 * Edit-mode override: when the request has ?editMode=1 we force
 * `keyboard: false` regardless of what the page record says, because
 * reveal.js's keyboard handler swallows arrow-keys / backspace inside
 * CKEditor when visual_editor is rendering the FE in its iframe.
 */
final class RevealJsConfigProcessor implements DataProcessorInterface
{
    /**
     * Map of TCA column => reveal.js option spec.
     *   key:     reveal.js JSON key
     *   cast:    bool | int | float | string
     *   default: applied when the column is null / unset (matches reveal.js defaults)
     *
     * Function-typed reveal.js options (autoSlideMethod, autoAnimateMatcher,
     * keyboardCondition function form) are not exposed — they don't map to
     * an editor-friendly TCA field.
     */
    private const OPTIONS = [
        // Navigation & UI
        'tx_revealjseditor_controls' => ['key' => 'controls', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_controlstutorial' => ['key' => 'controlsTutorial', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_controlslayout' => ['key' => 'controlsLayout', 'cast' => 'string', 'default' => 'bottom-right'],
        'tx_revealjseditor_controlsbackarrows' => ['key' => 'controlsBackArrows', 'cast' => 'string', 'default' => 'faded'],
        'tx_revealjseditor_progress' => ['key' => 'progress', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_slidenumber' => ['key' => 'slideNumber', 'cast' => 'string', 'default' => 'false'],
        'tx_revealjseditor_showslidenumber' => ['key' => 'showSlideNumber', 'cast' => 'string', 'default' => 'all'],
        'tx_revealjseditor_keyboard' => ['key' => 'keyboard', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_overview' => ['key' => 'overview', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_touch' => ['key' => 'touch', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_mousewheel' => ['key' => 'mouseWheel', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_navigationmode' => ['key' => 'navigationMode', 'cast' => 'string', 'default' => 'default'],
        'tx_revealjseditor_embedded' => ['key' => 'embedded', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_help' => ['key' => 'help', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_pause' => ['key' => 'pause', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_previewlinks' => ['key' => 'previewLinks', 'cast' => 'bool', 'default' => false],

        // URL / history
        'tx_revealjseditor_hash' => ['key' => 'hash', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_hashonebasedindex' => ['key' => 'hashOneBasedIndex', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_history' => ['key' => 'history', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_fragmentinurl' => ['key' => 'fragmentInURL', 'cast' => 'bool', 'default' => true],

        // Layout
        'tx_revealjseditor_center' => ['key' => 'center', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_viewdistance' => ['key' => 'viewDistance', 'cast' => 'int', 'default' => 3],

        // Auto-advance
        'tx_revealjseditor_autoslide' => ['key' => 'autoSlide', 'cast' => 'int', 'default' => 0],
        'tx_revealjseditor_autoslidestoppable' => ['key' => 'autoSlideStoppable', 'cast' => 'bool', 'default' => true],

        // Transitions
        'tx_revealjseditor_transition' => ['key' => 'transition', 'cast' => 'string', 'default' => 'slide'],
        'tx_revealjseditor_transitionspeed' => ['key' => 'transitionSpeed', 'cast' => 'string', 'default' => 'default'],
        'tx_revealjseditor_backgroundtransition' => ['key' => 'backgroundTransition', 'cast' => 'string', 'default' => 'fade'],

        // Fragments / animations
        'tx_revealjseditor_fragments' => ['key' => 'fragments', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_autoanimate' => ['key' => 'autoAnimate', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_autoanimateeasing' => ['key' => 'autoAnimateEasing', 'cast' => 'string', 'default' => 'ease'],
        'tx_revealjseditor_autoanimateduration' => ['key' => 'autoAnimateDuration', 'cast' => 'float', 'default' => 1.0],
        'tx_revealjseditor_autoanimateunmatched' => ['key' => 'autoAnimateUnmatched', 'cast' => 'bool', 'default' => true],

        // Loop / direction
        'tx_revealjseditor_loop' => ['key' => 'loop', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_rtl' => ['key' => 'rtl', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_shuffle' => ['key' => 'shuffle', 'cast' => 'bool', 'default' => false],

        // Speaker / print
        'tx_revealjseditor_shownotes' => ['key' => 'showNotes', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_pdfmaxpagesperslide' => ['key' => 'pdfMaxPagesPerSlide', 'cast' => 'int', 'default' => 0],
        'tx_revealjseditor_pdfseparatefragments' => ['key' => 'pdfSeparateFragments', 'cast' => 'bool', 'default' => true],

        // Media
        'tx_revealjseditor_autoplaymedia' => ['key' => 'autoPlayMedia', 'cast' => 'string', 'default' => 'default'],
        'tx_revealjseditor_preloadiframes' => ['key' => 'preloadIframes', 'cast' => 'string', 'default' => 'default'],

        // Scroll mode
        'tx_revealjseditor_view' => ['key' => 'view', 'cast' => 'string', 'default' => 'default'],
        'tx_revealjseditor_scrolllayout' => ['key' => 'scrollLayout', 'cast' => 'string', 'default' => 'full'],
        'tx_revealjseditor_scrollsnap' => ['key' => 'scrollSnap', 'cast' => 'string', 'default' => 'mandatory'],
        'tx_revealjseditor_scrollprogress' => ['key' => 'scrollProgress', 'cast' => 'string', 'default' => 'auto'],
        'tx_revealjseditor_scrollactivationwidth' => ['key' => 'scrollActivationWidth', 'cast' => 'int', 'default' => 435],
    ];

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $row = $cObj->data;
        $config = [];

        foreach (self::OPTIONS as $column => $spec) {
            $rawValue = $row[$column] ?? null;
            $value = ($rawValue === null || $rawValue === '') ? $spec['default'] : $rawValue;

            $config[$spec['key']] = match ($spec['cast']) {
                'bool' => (bool)$value,
                'int' => (int)$value,
                'float' => (float)$value,
                default => (string)$value,
            };
        }

        // Special-case selects whose "off" item is a string but reveal.js wants false / null.
        if ($config['slideNumber'] === 'false' || $config['slideNumber'] === '') {
            $config['slideNumber'] = false;
        }
        foreach (['autoPlayMedia', 'preloadIframes'] as $tristate) {
            if (($config[$tristate] ?? '') === 'default') {
                $config[$tristate] = null;
            } else {
                $config[$tristate] = $config[$tristate] === 'always';
            }
        }
        // pdfMaxPagesPerSlide=0 means "no limit" → reveal.js wants Infinity, but
        // JSON has no Infinity. Convention: 0 stays 0 in JSON; reveal.js treats
        // 0 as "no per-slide cap" via its own fallback.

        // Edit-mode safety: never let reveal.js intercept keyboard input
        // inside the visual_editor iframe (CKEditor / form fields).
        try {
            $request = $cObj->getRequest();
            if (($request->getQueryParams()['editMode'] ?? '') === '1') {
                $config['keyboard'] = false;
            }
        } catch (\Throwable) {
            // No request available in some BE contexts — leave config as-is.
        }

        $as = $processorConfiguration['as'] ?? 'revealjsConfig';
        $processedData[$as] = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $processedData;
    }
}
