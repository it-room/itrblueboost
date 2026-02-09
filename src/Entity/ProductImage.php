<?php

declare(strict_types=1);

namespace Itrblueboost\Entity;

use ObjectModel;
use Shop;

/**
 * AI Product Image entity.
 *
 * Manages AI-generated images for products.
 */
class ProductImage extends ObjectModel
{
    /** @var int Image ID */
    public $id;

    /** @var int Associated product ID */
    public $id_product;

    /** @var string Filename */
    public $filename;

    /** @var string Status (pending, accepted, rejected) */
    public $status;

    /** @var int Prompt ID used for generation */
    public $prompt_id;

    /** @var int|null PrestaShop image ID after acceptance */
    public $id_image;

    /** @var string|null Rejection reason */
    public $rejection_reason;

    /** @var string Creation date */
    public $date_add;

    /** @var string Modification date */
    public $date_upd;

    /**
     * Model definition.
     *
     * @var array<string, mixed>
     */
    public static $definition = [
        'table' => 'itrblueboost_product_image',
        'primary' => 'id_itrblueboost_product_image',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => [
            'id_product' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ],
            'filename' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 255,
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
            'id_image' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
            ],
            'rejection_reason' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isCleanHtml',
                'size' => 1000,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
            'date_upd' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
        ],
    ];

    /**
     * Constructor.
     *
     * @param int|null $id Image ID
     * @param int|null $idLang Language ID
     * @param int|null $idShop Shop ID
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        parent::__construct($id, $idLang, $idShop);
        Shop::addTableAssociation(self::$definition['table'], ['type' => 'shop']);
    }

    /**
     * Add a new image.
     *
     * @param bool $autoDate Auto-update dates
     * @param bool $nullValues Allow null values
     *
     * @return bool
     */
    public function add($autoDate = true, $nullValues = false)
    {
        if (empty($this->status)) {
            $this->status = 'pending';
        }

        return parent::add($autoDate, $nullValues);
    }

    /**
     * Get images for a product.
     *
     * @param int $idProduct Product ID
     * @param string|null $status Optional status filter
     * @param int|null $idShop Shop ID
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getByProduct(int $idProduct, ?string $status = null, ?int $idShop = null): array
    {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT i.*
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` i
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` ish
                    ON i.id_itrblueboost_product_image = ish.id_itrblueboost_product_image AND ish.id_shop = ' . $idShop . '
                WHERE i.id_product = ' . $idProduct;

        if ($status !== null) {
            $sql .= ' AND i.status = \'' . pSQL($status) . '\'';
        }

        $sql .= ' ORDER BY i.date_add DESC';

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Count images for a product.
     *
     * @param int $idProduct Product ID
     * @param string|null $status Optional status filter
     * @param int|null $idShop Shop ID
     *
     * @return int
     */
    public static function countByProduct(int $idProduct, ?string $status = null, ?int $idShop = null): int
    {
        $idShop = $idShop ?: (int) \Context::getContext()->shop->id;

        $sql = 'SELECT COUNT(*)
                FROM `' . _DB_PREFIX_ . self::$definition['table'] . '` i
                INNER JOIN `' . _DB_PREFIX_ . self::$definition['table'] . '_shop` ish
                    ON i.id_itrblueboost_product_image = ish.id_itrblueboost_product_image AND ish.id_shop = ' . $idShop . '
                WHERE i.id_product = ' . $idProduct;

        if ($status !== null) {
            $sql .= ' AND i.status = \'' . pSQL($status) . '\'';
        }

        return (int) \Db::getInstance()->getValue($sql);
    }

    /**
     * Delete all images for a product.
     *
     * @param int $idProduct Product ID
     *
     * @return bool
     */
    public static function deleteByProduct(int $idProduct): bool
    {
        $images = self::getByProduct($idProduct);

        foreach ($images as $imageData) {
            $image = new self((int) $imageData['id_itrblueboost_product_image']);
            if ($image->id) {
                $image->deleteFile();
                if (!$image->delete()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Delete the associated physical file.
     *
     * @return bool
     */
    public function deleteFile(): bool
    {
        if (empty($this->filename)) {
            return true;
        }

        $modulePath = _PS_MODULE_DIR_ . 'itrblueboost/';
        $pendingPath = $modulePath . 'uploads/pending/' . $this->filename;

        if (file_exists($pendingPath)) {
            return unlink($pendingPath);
        }

        return true;
    }

    /**
     * Get full path of the pending file.
     *
     * @return string
     */
    public function getPendingFilePath(): string
    {
        return _PS_MODULE_DIR_ . 'itrblueboost/uploads/pending/' . $this->filename;
    }

    /**
     * Get URL of the pending file.
     *
     * @return string
     */
    public function getPendingFileUrl(): string
    {
        return _MODULE_DIR_ . 'itrblueboost/uploads/pending/' . $this->filename;
    }

    /**
     * Check if the pending file exists.
     *
     * @return bool
     */
    public function pendingFileExists(): bool
    {
        return file_exists($this->getPendingFilePath());
    }
}
