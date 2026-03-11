<?php

declare(strict_types=1);

namespace Itrblueboost\Entity;

use Itrblueboost\Entity\Traits\FaqStatusTrait;
use ObjectModel;
use Shop;

/**
 * Category Content entity.
 *
 * Manages AI-generated descriptions for categories.
 */
class CategoryContent extends ObjectModel
{
    use FaqStatusTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    public const CONTENT_TYPE_DESCRIPTION = 'description';

    /** @var int Content ID */
    public $id;

    /** @var int Associated category ID */
    public $id_category;

    /** @var int|null API Content ID (from ITROOM API) */
    public $api_content_id;

    /** @var string Content type */
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

    /** @var string Generated short content (multilingual) */
    public $generated_content_short;

    /** @var string Generated meta title (multilingual) */
    public $meta_title;

    /** @var string Generated meta description (multilingual) */
    public $meta_description;

    /** @var string Generated meta keywords (multilingual) */
    public $meta_keywords;

    /** @var string Original meta title before generation */
    public $original_meta_title;

    /** @var string Original meta description before generation */
    public $original_meta_description;

    /** @var string Original meta keywords before generation */
    public $original_meta_keywords;

    /**
     * Model definition.
     *
     * @var array<string, mixed>
     */
    public static $definition = [
        'table' => 'itrblueboost_category_content',
        'primary' => 'id_itrblueboost_category_content',
        'multilang' => true,
        'multilang_shop' => false,
        'fields' => [
            'id_category' => [
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
            'generated_content_short' => [
                'type' => self::TYPE_HTML,
                'lang' => true,
                'validate' => 'isCleanHtml',
                'size' => 16777215,
            ],
            'meta_title' => [
                'type' => self::TYPE_STRING,
                'lang' => true,
                'validate' => 'isGenericName',
                'size' => 255,
            ],
            'meta_description' => [
                'type' => self::TYPE_STRING,
                'lang' => true,
                'validate' => 'isGenericName',
                'size' => 512,
            ],
            'meta_keywords' => [
                'type' => self::TYPE_STRING,
                'lang' => true,
                'validate' => 'isGenericName',
                'size' => 512,
            ],
            'original_meta_title' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 255,
            ],
            'original_meta_description' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 512,
            ],
            'original_meta_keywords' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 512,
            ],
        ],
    ];

    /** @var string Last error message from add/update */
    public $last_error = '';

    /**
     * @param int|null $id Content ID
     * @param int|null $idLang Language ID
     * @param int|null $idShop Shop ID
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        parent::__construct($id, $idLang, $idShop);
        Shop::addTableAssociation(self::$definition['table'], ['type' => 'shop']);
    }

    /**
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
     * Get contents for a category.
     *
     * @param int $idCategory Category ID
     * @param int $idLang Language ID
     * @param int|null $idShop Shop ID
     * @param bool $activeOnly Only active contents
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getByCategory(
        int $idCategory,
        int $idLang,
        ?int $idShop = null,
        bool $activeOnly = true
    ): array {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT c.*, cl.generated_content, cl.generated_content_short, cl.meta_title, cl.meta_description, cl.meta_keywords
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` c
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_lang` cl
                    ON c.id_itrblueboost_category_content = cl.id_itrblueboost_category_content AND cl.id_lang = ' . $idLang . '
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` cs
                    ON c.id_itrblueboost_category_content = cs.id_itrblueboost_category_content AND cs.id_shop = ' . $idShop . '
                WHERE c.id_category = ' . $idCategory;

        if ($activeOnly) {
            $sql .= ' AND c.active = 1 AND c.status = \'' . pSQL(self::STATUS_ACCEPTED) . '\'';
        }

        $sql .= ' ORDER BY c.date_add DESC';

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Get content by API Content ID.
     *
     * @param int $apiContentId API Content ID
     *
     * @return CategoryContent|null
     */
    public static function getByApiContentId(int $apiContentId): ?CategoryContent
    {
        $sql = 'SELECT id_itrblueboost_category_content FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                WHERE `api_content_id` = ' . $apiContentId;

        $id = \Db::getInstance()->getValue($sql);

        if ($id) {
            return new self((int) $id);
        }

        return null;
    }

    /**
     * Delete all contents for a category.
     *
     * @param int $idCategory Category ID
     *
     * @return bool
     */
    public static function deleteByCategory(int $idCategory): bool
    {
        $contents = self::getByCategory($idCategory, 1, null, false);

        foreach ($contents as $contentData) {
            $content = new self((int) $contentData['id_itrblueboost_category_content']);
            if (!$content->delete()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Count contents for a category.
     *
     * @param int $idCategory Category ID
     * @param int|null $idShop Shop ID
     *
     * @return int
     */
    public static function countByCategory(int $idCategory, ?int $idShop = null): int
    {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT COUNT(*)
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` c
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` cs
                    ON c.id_itrblueboost_category_content = cs.id_itrblueboost_category_content AND cs.id_shop = ' . $idShop . '
                WHERE c.id_category = ' . $idCategory;

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
     * Get all category contents with category info.
     *
     * @param int $idLang Language ID
     * @param int|null $idShop Shop ID
     * @param string|null $statusFilter Status filter
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

        $sql = 'SELECT c.*, cl.generated_content, cl.generated_content_short, cl.meta_title, cl.meta_description, cl.meta_keywords, cat_l.name as category_name
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` c
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_lang` cl
                    ON c.id_itrblueboost_category_content = cl.id_itrblueboost_category_content AND cl.id_lang = ' . $idLang . '
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` cs
                    ON c.id_itrblueboost_category_content = cs.id_itrblueboost_category_content AND cs.id_shop = ' . $idShop . '
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cat_l
                    ON c.id_category = cat_l.id_category AND cat_l.id_lang = ' . $idLang . ' AND cat_l.id_shop = ' . $idShop . '
                WHERE 1';

        if ($statusFilter !== null && $statusFilter !== '') {
            $sql .= ' AND c.status = \'' . pSQL($statusFilter) . '\'';
        }

        $sql .= ' ORDER BY c.date_add DESC';
        $sql .= ' LIMIT ' . $offset . ', ' . $limit;

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Count all category contents.
     *
     * @param int|null $idShop Shop ID
     * @param string|null $statusFilter Status filter
     *
     * @return int
     */
    public static function countAllContents(?int $idShop = null, ?string $statusFilter = null): int
    {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT COUNT(*)
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` c
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` cs
                    ON c.id_itrblueboost_category_content = cs.id_itrblueboost_category_content AND cs.id_shop = ' . $idShop . '
                WHERE 1';

        if ($statusFilter !== null && $statusFilter !== '') {
            $sql .= ' AND c.status = \'' . pSQL($statusFilter) . '\'';
        }

        return (int) \Db::getInstance()->getValue($sql);
    }
}
