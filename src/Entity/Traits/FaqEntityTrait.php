<?php

declare(strict_types=1);

namespace Itrblueboost\Entity\Traits;

/**
 * Shared methods for FAQ entities (ProductFaq, CategoryFaq).
 *
 * Requires the using class to have:
 * - property $api_faq_id
 * - static $definition with 'table' and 'primary' keys
 */
trait FaqEntityTrait
{
    /**
     * Check if this FAQ has an associated API ID.
     *
     * @return bool
     */
    public function hasApiFaqId(): bool
    {
        return !empty($this->api_faq_id) && $this->api_faq_id > 0;
    }

    /**
     * Update FAQ positions.
     *
     * @param array<int, int> $positions Array [faq_id => new_position]
     *
     * @return bool
     */
    public static function updatePositions(array $positions): bool
    {
        $db = \Db::getInstance();
        $primaryKey = self::$definition['primary'];

        foreach ($positions as $idFaq => $position) {
            $result = $db->update(
                self::$definition['table'],
                ['position' => (int) $position],
                $primaryKey . ' = ' . (int) $idFaq
            );

            if (!$result) {
                return false;
            }
        }

        return true;
    }
}
