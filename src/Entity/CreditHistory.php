<?php

declare(strict_types=1);

namespace Itrblueboost\Entity;

use ObjectModel;

/**
 * Credit History entity.
 *
 * Tracks credit consumption for analytics.
 */
class CreditHistory extends ObjectModel
{
    /** @var int ID */
    public $id;

    /** @var string Service code (faq, category_faq, image) */
    public $service_code;

    /** @var int Credits used */
    public $credits_used;

    /** @var int Credits remaining after operation */
    public $credits_remaining;

    /** @var int|null Related entity ID (product_id, category_id) */
    public $entity_id;

    /** @var string|null Entity type (product, category) */
    public $entity_type;

    /** @var string|null Additional details */
    public $details;

    /** @var string Creation date */
    public $date_add;

    /**
     * Model definition.
     *
     * @var array<string, mixed>
     */
    public static $definition = [
        'table' => 'itrblueboost_credit_history',
        'primary' => 'id_itrblueboost_credit_history',
        'fields' => [
            'service_code' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
                'required' => true,
            ],
            'credits_used' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'required' => true,
            ],
            'credits_remaining' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
            ],
            'entity_id' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'allow_null' => true,
            ],
            'entity_type' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
                'allow_null' => true,
            ],
            'details' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 255,
                'allow_null' => true,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
        ],
    ];

    /**
     * Log credit usage.
     *
     * @param string $serviceCode Service code
     * @param int $creditsUsed Credits consumed
     * @param int $creditsRemaining Credits remaining
     * @param int|null $entityId Related entity ID
     * @param string|null $entityType Entity type
     * @param string|null $details Additional info
     *
     * @return CreditHistory|null
     */
    public static function log(
        string $serviceCode,
        int $creditsUsed,
        int $creditsRemaining,
        ?int $entityId = null,
        ?string $entityType = null,
        ?string $details = null
    ): ?CreditHistory {
        if ($creditsUsed <= 0) {
            return null;
        }

        $history = new self();
        $history->service_code = $serviceCode;
        $history->credits_used = $creditsUsed;
        $history->credits_remaining = $creditsRemaining;
        $history->entity_id = $entityId;
        $history->entity_type = $entityType;
        $history->details = $details;

        if ($history->add()) {
            return $history;
        }

        return null;
    }

    /**
     * Get recent history.
     *
     * @param int $limit Number of records
     * @param int $offset Offset for pagination
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRecentHistory(int $limit = 20, int $offset = 0): array
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                ORDER BY date_add DESC, id_itrblueboost_credit_history DESC
                LIMIT ' . (int) $offset . ', ' . (int) $limit;

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Get statistics by service.
     *
     * @param int $days Number of days to look back (0 = all time)
     *
     * @return array<string, array{total_credits: int, count: int}>
     */
    public static function getStatsByService(int $days = 30): array
    {
        $whereClause = '';
        if ($days > 0) {
            $whereClause = 'WHERE date_add >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)';
        }

        $sql = 'SELECT
                    service_code,
                    SUM(credits_used) as total_credits,
                    COUNT(*) as count
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                ' . $whereClause . '
                GROUP BY service_code
                ORDER BY total_credits DESC';

        $results = \Db::getInstance()->executeS($sql);

        $stats = [];
        if ($results) {
            foreach ($results as $row) {
                $stats[$row['service_code']] = [
                    'total_credits' => (int) $row['total_credits'],
                    'count' => (int) $row['count'],
                ];
            }
        }

        return $stats;
    }

    /**
     * Get daily consumption for chart.
     *
     * @param int $days Number of days
     *
     * @return array<int, array{date: string, credits: int}>
     */
    public static function getDailyConsumption(int $days = 30): array
    {
        $sql = 'SELECT
                    DATE(date_add) as date,
                    SUM(credits_used) as credits
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                WHERE date_add >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)
                GROUP BY DATE(date_add)
                ORDER BY date ASC';

        $results = \Db::getInstance()->executeS($sql);

        return $results ?: [];
    }

    /**
     * Get total credits used.
     *
     * @param int $days Number of days (0 = all time)
     *
     * @return int
     */
    public static function getTotalCreditsUsed(int $days = 0): int
    {
        $whereClause = '';
        if ($days > 0) {
            $whereClause = 'WHERE date_add >= DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)';
        }

        $sql = 'SELECT SUM(credits_used) FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` ' . $whereClause;

        return (int) \Db::getInstance()->getValue($sql);
    }

    /**
     * Get service name for display.
     *
     * @param string $code Service code
     *
     * @return string
     */
    public static function getServiceName(string $code): string
    {
        $names = [
            'faq' => 'FAQ Produit',
            'product_faq' => 'FAQ Produit',
            'category_faq' => 'FAQ Catégorie',
            'image' => 'Image IA',
        ];

        return $names[$code] ?? $code;
    }
}
