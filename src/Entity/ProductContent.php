<?php

declare(strict_types=1);

namespace Itrblueboost\Entity;

use ObjectModel;
use Shop;

/**
 * Product Content entity.
 *
 * Manages AI-generated descriptions and short descriptions for products.
 */
class ProductContent extends ObjectModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    public const CONTENT_TYPE_DESCRIPTION = 'description';
    public const CONTENT_TYPE_SHORT_DESCRIPTION = 'description_short';

    /** @var int Content ID */
    public $id;

    /** @var int Associated product ID */
    public $id_product;

    /** @var int|null API Content ID (from ITROOM API) */
    public $api_content_id;

    /** @var string Content type (description, description_short) */
    public $content_type;

    /** @var string Status (pending, accepted, rejected) */
    public $status;

    /** @var int Prompt ID used for generation */
    public $prompt_id;

    /** @var bool Active/inactive status */
    public $active;

    /** @var string Creation date */
    public $date_add;

    /** @var string Modification date */
    public $date_upd;

    /** @var string Generated content (multilingual) */
    public $generated_content;

    /**
     * Model definition.
     *
     * @var array<string, mixed>
     */
    public static $definition = [
        'table' => 'itrblueboost_product_content',
        'primary' => 'id_itrblueboost_product_content',
        'multilang' => true,
        'multilang_shop' => false,
        'fields' => [
            'id_product' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            'api_content_id' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
            ],
            'content_type' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 20,
                'required' => true,
            ],
            'status' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 20,
            ],
            'prompt_id' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
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
            'generated_content' => [
                'type' => self::TYPE_HTML,
                'lang' => true,
                'validate' => 'isCleanHtml',
                'required' => true,
                'size' => 16777215,
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param int|null $id Content ID
     * @param int|null $idLang Language ID
     * @param int|null $idShop Shop ID
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        parent::__construct($id, $idLang, $idShop);
        Shop::addTableAssociation(self::$definition['table'], ['type' => 'shop']);
    }

    /** @var string Last error message from add/update */
    public $last_error = '';

    /**
     * Add a new content.
     *
     * @param bool $autoDate Auto-update dates
     * @param bool $nullValues Allow null values
     *
     * @return bool
     */
    public function add($autoDate = true, $nullValues = true)
    {
        if (empty($this->status)) {
            $this->status = self::STATUS_PENDING;
        }

        if (empty($this->content_type)) {
            $this->content_type = self::CONTENT_TYPE_DESCRIPTION;
        }

        // Validate before insert to capture errors
        $validationErrors = $this->validateFields(false, true);
        if ($validationErrors !== true) {
            $this->last_error = 'Validation: ' . $validationErrors;

            return false;
        }

        $langErrors = $this->validateFieldsLang(false, true);
        if ($langErrors !== true) {
            $this->last_error = 'Lang validation: ' . $langErrors;

            return false;
        }

        $result = parent::add($autoDate, $nullValues);

        if (!$result) {
            $this->last_error = 'SQL: ' . \Db::getInstance()->getMsgError();
        }

        return $result;
    }

    /**
     * Get contents for a product.
     *
     * @param int $idProduct Product ID
     * @param int $idLang Language ID
     * @param int|null $idShop Shop ID
     * @param bool $activeOnly Only active contents
     * @param string|null $contentType Filter by content type
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getByProduct(
        int $idProduct,
        int $idLang,
        ?int $idShop = null,
        bool $activeOnly = true,
        ?string $contentType = null
    ): array {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT c.*, cl.generated_content
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` c
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_lang` cl
                    ON c.id_itrblueboost_product_content = cl.id_itrblueboost_product_content AND cl.id_lang = ' . $idLang . '
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` cs
                    ON c.id_itrblueboost_product_content = cs.id_itrblueboost_product_content AND cs.id_shop = ' . $idShop . '
                WHERE c.id_product = ' . $idProduct;

        if ($activeOnly) {
            $sql .= ' AND c.active = 1 AND c.status = \'' . pSQL(self::STATUS_ACCEPTED) . '\'';
        }

        if ($contentType !== null) {
            $sql .= ' AND c.content_type = \'' . pSQL($contentType) . '\'';
        }

        $sql .= ' ORDER BY c.date_add DESC';

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Get content by API Content ID.
     *
     * @param int $apiContentId API Content ID
     *
     * @return ProductContent|null
     */
    public static function getByApiContentId(int $apiContentId): ?ProductContent
    {
        $sql = 'SELECT id_itrblueboost_product_content FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                WHERE `api_content_id` = ' . $apiContentId;

        $id = \Db::getInstance()->getValue($sql);

        if ($id) {
            return new self((int) $id);
        }

        return null;
    }

    /**
     * Delete all contents for a product.
     *
     * @param int $idProduct Product ID
     *
     * @return bool
     */
    public static function deleteByProduct(int $idProduct): bool
    {
        $contents = self::getByProduct($idProduct, 1, null, false);

        foreach ($contents as $contentData) {
            $content = new self((int) $contentData['id_itrblueboost_product_content']);
            if (!$content->delete()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Count contents for a product.
     *
     * @param int $idProduct Product ID
     * @param int|null $idShop Shop ID
     * @param string|null $contentType Filter by content type
     *
     * @return int
     */
    public static function countByProduct(int $idProduct, ?int $idShop = null, ?string $contentType = null): int
    {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT COUNT(*)
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` c
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` cs
                    ON c.id_itrblueboost_product_content = cs.id_itrblueboost_product_content AND cs.id_shop = ' . $idShop . '
                WHERE c.id_product = ' . $idProduct;

        if ($contentType !== null) {
            $sql .= ' AND c.content_type = \'' . pSQL($contentType) . '\'';
        }

        return (int) \Db::getInstance()->getValue($sql);
    }

    /**
     * Check if this content has an associated API ID.
     *
     * @return bool
     */
    public function hasApiContentId(): bool
    {
        return !empty($this->api_content_id) && $this->api_content_id > 0;
    }

    /**
     * Check if this content is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this content is accepted.
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Get content type label.
     *
     * @return string
     */
    public function getContentTypeLabel(): string
    {
        if ($this->content_type === self::CONTENT_TYPE_SHORT_DESCRIPTION) {
            return 'Description courte';
        }

        return 'Description';
    }

    /**
     * Get all contents for the "All Product Contents" view.
     *
     * @param int $idLang Language ID
     * @param int|null $idShop Shop ID
     * @param string|null $statusFilter Filter by status
     * @param int $limit Limit
     * @param int $offset Offset
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllContents(
        int $idLang,
        ?int $idShop = null,
        ?string $statusFilter = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT c.*, cl.generated_content, p.reference as product_reference, pl.name as product_name
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` c
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_lang` cl
                    ON c.id_itrblueboost_product_content = cl.id_itrblueboost_product_content AND cl.id_lang = ' . $idLang . '
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` cs
                    ON c.id_itrblueboost_product_content = cs.id_itrblueboost_product_content AND cs.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON c.id_product = p.id_product
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON c.id_product = pl.id_product AND pl.id_lang = ' . $idLang . ' AND pl.id_shop = ' . $idShop . '
                WHERE 1';

        if ($statusFilter !== null && $statusFilter !== '') {
            $sql .= ' AND c.status = \'' . pSQL($statusFilter) . '\'';
        }

        $sql .= ' ORDER BY c.date_add DESC';
        $sql .= ' LIMIT ' . $offset . ', ' . $limit;

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Count all contents.
     *
     * @param int|null $idShop Shop ID
     * @param string|null $statusFilter Filter by status
     *
     * @return int
     */
    public static function countAllContents(?int $idShop = null, ?string $statusFilter = null): int
    {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT COUNT(*)
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` c
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` cs
                    ON c.id_itrblueboost_product_content = cs.id_itrblueboost_product_content AND cs.id_shop = ' . $idShop . '
                WHERE 1';

        if ($statusFilter !== null && $statusFilter !== '') {
            $sql .= ' AND c.status = \'' . pSQL($statusFilter) . '\'';
        }

        return (int) \Db::getInstance()->getValue($sql);
    }
}
