<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin\Traits;

/**
 * Trait for multilang field helpers.
 */
trait MultilangHelperTrait
{
    /**
     * Resolve a multilang field to a single string for the given language.
     *
     * @param mixed $field Multilang field (array or string)
     * @param int $idLang Language ID
     *
     * @return string
     */
    private function resolveMultilangText($field, int $idLang): string
    {
        if (is_array($field)) {
            return (string) ($field[$idLang] ?? reset($field));
        }

        return (string) $field;
    }

    /**
     * Check if a multilang field has changed.
     *
     * @param mixed $old Old value
     * @param mixed $new New value
     *
     * @return bool
     */
    private function hasMultilangChanged($old, $new): bool
    {
        if (!is_array($old) || !is_array($new)) {
            return $old !== $new;
        }

        foreach ($new as $langId => $value) {
            if (!isset($old[$langId]) || $old[$langId] !== $value) {
                return true;
            }
        }

        return false;
    }
}
