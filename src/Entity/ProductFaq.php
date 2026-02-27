<?php

declare(strict_types=1);

namespace Itrblueboost\Entity;

use Itrblueboost\Entity\Traits\FaqEntityTrait;
use Itrblueboost\Entity\Traits\FaqStatusTrait;
use ObjectModel;
use Shop;

/**
 * Product FAQ entity.
 *
 * Manages FAQ questions/answers associated with products.
 */
class ProductFaq extends ObjectModel
{
    use FaqEntityTrait;
    use FaqStatusTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    /** @var int FAQ ID */
    public $id;

    /** @var int Associated product ID */
    public $id_product;

    /** @var int|null API FAQ ID (from ITROOM API) */
    public $api_faq_id;

    /** @var string Status (pending, accepted, rejected) */
    public $status;

    /** @var int Position for sorting */
    public $position;

    /** @var bool Active/inactive status */
    public $active;

    /** @var string Creation date */
    public $date_add;

    /** @var string Modification date */
    public $date_upd;

    /** @var string Question (multilingual) */
    public $question;

    /** @var string Answer (multilingual) */
    public $answer;

    /**
     * Model definition.
     *
     * @var array<string, mixed>
     */
    public static $definition = [
        'table' => 'itrblueboost_product_faq',
        'primary' => 'id_itrblueboost_product_faq',
        'multilang' => true,
        'multilang_shop' => false,
        'fields' => [
            'id_product' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            'api_faq_id' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'allow_null' => true,
            ],
            'status' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 20,
            ],
            'position' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
            ],
            'active' => [
                'type' => self::TYPE_BOOL,
                'validate' => 'isBool',
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
            'date_upd' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
            'question' => [
                'type' => self::TYPE_HTML,
                'lang' => true,
                'validate' => 'isCleanHtml',
                'required' => true,
                'size' => 65535,
            ],
            'answer' => [
                'type' => self::TYPE_HTML,
                'lang' => true,
                'validate' => 'isCleanHtml',
                'required' => false,
                'size' => 65535,
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param int|null $id FAQ ID
     * @param int|null $idLang Language ID
     * @param int|null $idShop Shop ID
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        parent::__construct($id, $idLang, $idShop);
        Shop::addTableAssociation(self::$definition['table'], ['type' => 'shop']);
    }

    /**
     * Add a new FAQ.
     *
     * @param bool $autoDate Auto-update dates
     * @param bool $nullValues Allow null values
     *
     * @return bool
     */
    public function add($autoDate = true, $nullValues = true)
    {
        if ($this->position === null || $this->position === 0) {
            $this->position = self::getHighestPosition((int) $this->id_product) + 1;
        }

        if (empty($this->status)) {
            $this->status = self::STATUS_PENDING;
        }

        return parent::add($autoDate, $nullValues);
    }

    /**
     * Get highest position for a product.
     *
     * @param int $idProduct Product ID
     *
     * @return int
     */
    public static function getHighestPosition(int $idProduct): int
    {
        $sql = 'SELECT MAX(position) FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                WHERE `id_product` = ' . $idProduct;

        return (int) \Db::getInstance()->getValue($sql);
    }

    /**
     * Get FAQs for a product.
     *
     * @param int $idProduct Product ID
     * @param int $idLang Language ID
     * @param int|null $idShop Shop ID
     * @param bool $activeOnly Only active FAQs
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getByProduct(int $idProduct, int $idLang, ?int $idShop = null, bool $activeOnly = true): array
    {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT f.*, fl.question, fl.answer
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` f
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_lang` fl
                    ON f.id_itrblueboost_product_faq = fl.id_itrblueboost_product_faq AND fl.id_lang = ' . $idLang . '
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` fs
                    ON f.id_itrblueboost_product_faq = fs.id_itrblueboost_product_faq AND fs.id_shop = ' . $idShop . '
                WHERE f.id_product = ' . $idProduct;

        if ($activeOnly) {
            $sql .= ' AND f.active = 1 AND (f.status = \'' . pSQL(self::STATUS_ACCEPTED) . '\' OR f.status IS NULL OR f.status = \'\')';
        }

        $sql .= ' ORDER BY f.position ASC';

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Get FAQ by API FAQ ID.
     *
     * @param int $apiFaqId API FAQ ID
     *
     * @return ProductFaq|null
     */
    public static function getByApiFaqId(int $apiFaqId): ?ProductFaq
    {
        $sql = 'SELECT id_itrblueboost_product_faq FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                WHERE `api_faq_id` = ' . $apiFaqId;

        $id = \Db::getInstance()->getValue($sql);

        if ($id) {
            return new self((int) $id);
        }

        return null;
    }

    /**
     * Delete all FAQs for a product.
     *
     * @param int $idProduct Product ID
     *
     * @return bool
     */
    public static function deleteByProduct(int $idProduct): bool
    {
        $faqs = self::getByProduct($idProduct, 1, null, false);

        foreach ($faqs as $faqData) {
            $faq = new self((int) $faqData['id_itrblueboost_product_faq']);
            if (!$faq->delete()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Count FAQs for a product.
     *
     * @param int $idProduct Product ID
     * @param int|null $idShop Shop ID
     *
     * @return int
     */
    public static function countByProduct(int $idProduct, ?int $idShop = null): int
    {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT COUNT(*)
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` f
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` fs
                    ON f.id_itrblueboost_product_faq = fs.id_itrblueboost_product_faq AND fs.id_shop = ' . $idShop . '
                WHERE f.id_product = ' . $idProduct;

        return (int) \Db::getInstance()->getValue($sql);
    }

}
