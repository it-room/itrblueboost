<?php

declare(strict_types=1);

namespace Itrblueboost\Entity;

use Db;
use ObjectModel;
use Shop;

/**
 * Generation job entity for async API processing.
 *
 * Tracks the status and progress of long-running generation jobs
 * (images, FAQs, content) to avoid HTTP 504 timeouts.
 */
class GenerationJob extends ObjectModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_IMAGE = 'image';
    public const TYPE_FAQ = 'faq';
    public const TYPE_CONTENT = 'content';

    /** @var int */
    public $id;

    /** @var string Job type (image, faq, content) */
    public $job_type;

    /** @var string Current status */
    public $status;

    /** @var int Progress percentage (0-100) */
    public $progress;

    /** @var string Human-readable progress label */
    public $progress_label;

    /** @var int|null Associated product ID */
    public $id_product;

    /** @var int|null Associated category ID */
    public $id_category;

    /** @var string JSON-encoded request parameters */
    public $request_data;

    /** @var string|null JSON-encoded response data */
    public $response_data;

    /** @var string|null Error message if failed */
    public $error_message;

    /** @var string Creation date */
    public $date_add;

    /** @var string Modification date */
    public $date_upd;

    /**
     * @var array<string, mixed>
     */
    public static $definition = [
        'table' => 'itrblueboost_generation_job',
        'primary' => 'id_itrblueboost_generation_job',
        'multilang' => false,
        'multilang_shop' => false,
        'fields' => [
            'job_type' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 50,
            ],
            'status' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 20,
            ],
            'progress' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
            ],
            'progress_label' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 255,
            ],
            'id_product' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
            ],
            'id_category' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
            ],
            'request_data' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isCleanHtml',
            ],
            'response_data' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isCleanHtml',
            ],
            'error_message' => [
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
     * @param int|null $id
     * @param int|null $idLang
     * @param int|null $idShop
     */
    public function __construct($id = null, $idLang = null, $idShop = null)
    {
        parent::__construct($id, $idLang, $idShop);
        Shop::addTableAssociation(self::$definition['table'], ['type' => 'shop']);
    }

    /**
     * @param bool $autoDate
     * @param bool $nullValues
     *
     * @return bool
     */
    public function add($autoDate = true, $nullValues = true)
    {
        if (empty($this->status)) {
            $this->status = self::STATUS_PENDING;
        }

        if (empty($this->progress)) {
            $this->progress = 0;
        }

        return parent::add($autoDate, $nullValues);
    }

    /**
     * Update job progress.
     *
     * @param int $progress Percentage (0-100)
     * @param string $label Human-readable label
     */
    public function updateProgress(int $progress, string $label): bool
    {
        $this->progress = min(100, max(0, $progress));
        $this->progress_label = $label;

        return (bool) $this->update();
    }

    /**
     * Mark job as processing.
     *
     * @param string $label Progress label
     */
    public function markProcessing(string $label = 'Processing...'): bool
    {
        $this->status = self::STATUS_PROCESSING;
        $this->progress = 10;
        $this->progress_label = $label;

        return (bool) $this->update();
    }

    /**
     * Mark job as completed.
     *
     * @param array<string, mixed>|null $responseData Response data to store
     */
    public function markCompleted(?array $responseData = null): bool
    {
        $this->status = self::STATUS_COMPLETED;
        $this->progress = 100;
        $this->progress_label = 'Completed';

        if ($responseData !== null) {
            $this->response_data = json_encode($responseData);
        }

        return (bool) $this->update();
    }

    /**
     * Mark job as failed.
     *
     * @param string $errorMessage Error description
     */
    public function markFailed(string $errorMessage): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $errorMessage;

        return (bool) $this->update();
    }

    /**
     * Get decoded request data.
     *
     * @return array<string, mixed>
     */
    public function getRequestDataArray(): array
    {
        if (empty($this->request_data)) {
            return [];
        }

        $decoded = json_decode($this->request_data, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get decoded response data.
     *
     * @return array<string, mixed>
     */
    public function getResponseDataArray(): array
    {
        if (empty($this->response_data)) {
            return [];
        }

        $decoded = json_decode($this->response_data, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Clean up old completed/failed jobs older than given days.
     *
     * @param int $days Number of days to keep
     */
    public static function cleanOldJobs(int $days = 7): bool
    {
        $sql = 'DELETE FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                WHERE (`status` = \'' . pSQL(self::STATUS_COMPLETED) . '\'
                    OR `status` = \'' . pSQL(self::STATUS_FAILED) . '\')
                AND `date_add` < DATE_SUB(NOW(), INTERVAL ' . (int) $days . ' DAY)';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Find stale processing jobs (stuck for more than given minutes).
     *
     * @param int $minutes Timeout threshold
     *
     * @return array<int, array<string, mixed>>
     */
    public static function findStaleJobs(int $minutes = 10): array
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                WHERE `status` = \'' . pSQL(self::STATUS_PROCESSING) . '\'
                AND `date_upd` < DATE_SUB(NOW(), INTERVAL ' . (int) $minutes . ' MINUTE)';

        return Db::getInstance()->executeS($sql) ?: [];
    }
}
