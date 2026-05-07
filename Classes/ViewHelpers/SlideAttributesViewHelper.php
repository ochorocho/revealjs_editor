<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\ViewHelpers;

use Psr\Container\ContainerInterface;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Builds the per-slide reveal.js HTML attribute string from a tt_content
 * row's tx_revealjseditor_* fields.
 *
 * Used by `Pages/Revealjs.html` inside the `<f:for each="{content.main}" as="slide">`
 * loop to populate the wrapping `<section>`'s data-* attributes:
 *
 *   <section {reveal:slideAttributes(record: slide) -> f:format.raw()}>
 *     <f:render.record record="{slide}" />
 *   </section>
 *
 * The output is a space-separated key="value" string with HTML-attribute-safe
 * escaping. Empty values and values matching the spec's default are skipped
 * so the rendered markup stays minimal — reveal.js falls through to the
 * page-level config in `data-revealjs-options` for any attribute not present
 * on the section.
 *
 * Single source of truth: the SLIDE_OPTIONS map below maps every supported
 * tx_revealjseditor_* tt_content column to its data-* attribute, plus type
 * casting and the default value used to suppress redundant emission.
 */
final class SlideAttributesViewHelper extends AbstractViewHelper
{
    /**
     * Output is HTML-attribute markup; do not let Fluid double-escape.
     */
    protected $escapeOutput = false;

    /**
     * column => [
     *   'attr'    => HTML attribute name on <section>
     *   'cast'    => string | int | bool       (controls value formatting)
     *   'default' => value below which we skip emission (matches TCA default)
     * ]
     */
    private const SLIDE_OPTIONS = [
        // Identity / class / state
        // class and data-state are independent: class sits on <section> always;
        // data-state is reveal.js's "apply this class to the viewport when this
        // slide becomes active" mechanism.
        'tx_revealjseditor_class' => ['attr' => 'class', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_data_state' => ['attr' => 'data-state', 'cast' => 'string', 'default' => ''],

        // Transitions
        'tx_revealjseditor_transition' => ['attr' => 'data-transition', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_transition_speed' => ['attr' => 'data-transition-speed', 'cast' => 'string', 'default' => ''],

        // Backgrounds
        'tx_revealjseditor_bg_color' => ['attr' => 'data-background-color', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_bg_gradient' => ['attr' => 'data-background-gradient', 'cast' => 'string', 'default' => ''],
        // bg_image and bg_video are FAL relations on tt_content (TCA type=file).
        // The field value is the *count* of references; resolveFalUrl() looks
        // up the actual file and emits its public URL.
        'tx_revealjseditor_bg_image' => ['attr' => 'data-background-image', 'cast' => 'fal', 'default' => ''],
        'tx_revealjseditor_bg_video' => ['attr' => 'data-background-video', 'cast' => 'fal', 'default' => ''],
        'tx_revealjseditor_bg_iframe' => ['attr' => 'data-background-iframe', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_bg_size' => ['attr' => 'data-background-size', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_bg_position' => ['attr' => 'data-background-position', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_bg_repeat' => ['attr' => 'data-background-repeat', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_bg_opacity' => ['attr' => 'data-background-opacity', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_bg_transition' => ['attr' => 'data-background-transition', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_bg_video_loop' => ['attr' => 'data-background-video-loop', 'cast' => 'bool', 'default' => true],
        'tx_revealjseditor_bg_video_muted' => ['attr' => 'data-background-video-muted', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_bg_interactive' => ['attr' => 'data-background-interactive', 'cast' => 'bool', 'default' => false],

        // Slide behaviour
        'tx_revealjseditor_slide_autoslide' => ['attr' => 'data-autoslide', 'cast' => 'int', 'default' => 0],
        'tx_revealjseditor_slide_visibility' => ['attr' => 'data-visibility', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_slide_prevent_swipe' => ['attr' => 'data-prevent-swipe', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_slide_notes' => ['attr' => 'data-notes', 'cast' => 'string', 'default' => ''],

        // Auto-animate (per slide)
        'tx_revealjseditor_anim_enabled' => ['attr' => 'data-auto-animate', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_anim_id' => ['attr' => 'data-auto-animate-id', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_anim_restart' => ['attr' => 'data-auto-animate-restart', 'cast' => 'bool', 'default' => false],
        'tx_revealjseditor_anim_easing' => ['attr' => 'data-auto-animate-easing', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_anim_duration' => ['attr' => 'data-auto-animate-duration', 'cast' => 'string', 'default' => ''],
        'tx_revealjseditor_anim_unmatched' => ['attr' => 'data-auto-animate-unmatched', 'cast' => 'bool', 'default' => true],
    ];

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        // Accept either a TCA-array tt_content row OR a Domain\Record (PSR-11 ContainerInterface).
        // The render() method adapts both via $this->readField().
        $this->registerArgument('record', 'mixed', 'The tt_content slide row (array or Domain\Record)', true);
    }

    public function render(): string
    {
        $record = $this->arguments['record'];
        $attributes = [];

        foreach (self::SLIDE_OPTIONS as $column => $spec) {
            $value = $this->readField($record, $column);

            // FAL fields hold a reference count, not a URL. Resolve the
            // first reference to its public URL via FileRepository, then
            // fall through to the string-cast branch with the resolved URL.
            if ($spec['cast'] === 'fal') {
                $value = $this->resolveFalUrl($record, $column);
            }

            if ($value === null || $value === '') {
                continue;
            }

            switch ($spec['cast']) {
                case 'bool':
                    $bool = (bool)$value;
                    if ($bool === (bool)$spec['default']) {
                        continue 2;
                    }
                    $rendered = $bool ? 'true' : 'false';
                    break;
                case 'int':
                    $int = (int)$value;
                    if ($int === (int)$spec['default']) {
                        continue 2;
                    }
                    $rendered = (string)$int;
                    break;
                default:
                    $rendered = (string)$value;
                    if ($rendered === (string)$spec['default']) {
                        continue 2;
                    }
                    break;
            }

            $attributes[] = sprintf(
                '%s="%s"',
                $spec['attr'],
                htmlspecialchars($rendered, ENT_QUOTES | ENT_SUBSTITUTE),
            );
        }

        return implode(' ', $attributes);
    }

    /**
     * Read a column from either a TCA array or a Domain\Record (PSR-11).
     */
    private function readField(mixed $record, string $field): mixed
    {
        if (is_array($record)) {
            return $record[$field] ?? null;
        }
        if ($record instanceof ContainerInterface) {
            return $record->has($field) ? $record->get($field) : null;
        }
        if (is_object($record) && method_exists($record, 'get')) {
            try {
                return $record->get($field);
            } catch (\Throwable) {
                return null;
            }
        }
        return null;
    }

    /**
     * Resolve a FAL field on a tt_content row to the first referenced file's
     * public URL. Returns an empty string when no reference is set or the
     * file isn't reachable. Uses TYPO3's FileRepository — one DB query per
     * FAL field per slide (cheap for typical decks).
     */
    private function resolveFalUrl(mixed $record, string $field): string
    {
        $uid = $this->readUid($record);
        if ($uid <= 0) {
            return '';
        }

        try {
            /** @var FileRepository $fileRepository */
            $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
            $files = $fileRepository->findByRelation('tt_content', $field, $uid);
            if ($files === []) {
                return '';
            }
            $firstFile = $files[0];
            return (string)($firstFile->getPublicUrl() ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Read the slide's primary key. Falls back to 0 (which short-circuits
     * FAL resolution) when the record shape doesn't carry one.
     */
    private function readUid(mixed $record): int
    {
        if (is_array($record)) {
            return (int)($record['uid'] ?? 0);
        }
        if (is_object($record) && method_exists($record, 'getUid')) {
            return (int)$record->getUid();
        }
        if ($record instanceof ContainerInterface && $record->has('uid')) {
            return (int)$record->get('uid');
        }
        return 0;
    }
}
