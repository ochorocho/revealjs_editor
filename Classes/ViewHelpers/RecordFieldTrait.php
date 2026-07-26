<?php

declare(strict_types=1);

namespace Ochorocho\RevealJsEditor\ViewHelpers;

use Psr\Container\ContainerInterface;

/**
 * Shared reader for a single column of a slide record, accepting either a
 * legacy TCA array row or a v14 Domain\Record (which exposes fields through
 * PSR-11 has()/get() rather than array access). Used by both
 * SlideAttributesViewHelper and SlideNotesViewHelper.
 */
trait RecordFieldTrait
{
    protected function readField(mixed $record, string $field): mixed
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
}
