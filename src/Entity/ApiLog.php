<?php

declare(strict_types=1);

namespace Itrblueboost\Entity;

use ObjectModel;

/**
 * API Log entity.
 *
 * Stores API request/response logs for debugging.
 */
class ApiLog extends ObjectModel
{
    /** @var int Log ID */
    public $id;

    /** @var string HTTP method (GET, POST, PUT, DELETE) */
    public $method;

    /** @var string API endpoint */
    public $endpoint;

    /** @var string|null Request body (JSON) */
    public $request_body;

    /** @var string|null Request headers (JSON) */
    public $request_headers;

    /** @var int HTTP response code */
    public $response_code;

    /** @var string|null Response body */
    public $response_body;

    /** @var float Duration in seconds */
    public $duration;

    /** @var string|null Error message if any */
    public $error_message;

    /** @var string|null Context (product_faq, category_faq, image, etc.) */
    public $context;

    /** @var string Creation date */
    public $date_add;

    /**
     * Model definition.
     *
     * @var array<string, mixed>
     */
    public static $definition = [
        'table' => 'itrblueboost_api_log',
        'primary' => 'id_itrblueboost_api_log',
        'fields' => [
            'method' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 10,
                'required' => true,
            ],
            'endpoint' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isUrl',
                'size' => 500,
                'required' => true,
            ],
            'request_body' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isString',
                'allow_null' => true,
            ],
            'request_headers' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isString',
                'allow_null' => true,
            ],
            'response_code' => [
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
            ],
            'response_body' => [
                'type' => self::TYPE_HTML,
                'validate' => 'isString',
                'allow_null' => true,
            ],
            'duration' => [
                'type' => self::TYPE_FLOAT,
                'validate' => 'isFloat',
            ],
            'error_message' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 1000,
                'allow_null' => true,
            ],
            'context' => [
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 50,
                'allow_null' => true,
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
            ],
        ],
    ];

    /**
     * Log an API call.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array<string, mixed>|null $requestBody Request body
     * @param array<string, string> $requestHeaders Request headers
     * @param int $responseCode HTTP response code
     * @param string|null $responseBody Response body
     * @param float $duration Duration in seconds
     * @param string|null $errorMessage Error message
     * @param string|null $context Context identifier
     *
     * @return ApiLog|null
     */
    public static function log(
        string $method,
        string $endpoint,
        ?array $requestBody,
        array $requestHeaders,
        int $responseCode,
        ?string $responseBody,
        float $duration,
        ?string $errorMessage = null,
        ?string $context = null
    ): ?ApiLog {
        $log = new self();
        $log->method = strtoupper($method);
        $log->endpoint = $endpoint;
        $log->request_body = $requestBody ? json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;

        // Mask API key in headers
        $maskedHeaders = $requestHeaders;
        if (isset($maskedHeaders['X-API-Key'])) {
            $key = $maskedHeaders['X-API-Key'];
            $maskedHeaders['X-API-Key'] = substr($key, 0, 8) . '...' . substr($key, -4);
        }
        $log->request_headers = json_encode($maskedHeaders, JSON_PRETTY_PRINT);

        $log->response_code = $responseCode;
        $log->response_body = $responseBody;
        $log->duration = $duration;
        $log->error_message = $errorMessage;
        $log->context = $context;

        if ($log->add()) {
            return $log;
        }

        return null;
    }

    /**
     * Get recent logs.
     *
     * @param int $limit Number of logs to retrieve
     * @param int $offset Offset for pagination
     * @param string|null $context Filter by context
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRecentLogs(int $limit = 50, int $offset = 0, ?string $context = null): array
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`';

        if ($context) {
            $sql .= ' WHERE context = \'' . pSQL($context) . '\'';
        }

        $sql .= ' ORDER BY date_add DESC, id_itrblueboost_api_log DESC';
        $sql .= ' LIMIT ' . (int) $offset . ', ' . (int) $limit;

        return \Db::getInstance()->executeS($sql) ?: [];
    }

    /**
     * Count total logs.
     *
     * @param string|null $context Filter by context
     *
     * @return int
     */
    public static function countLogs(?string $context = null): int
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`';

        if ($context) {
            $sql .= ' WHERE context = \'' . pSQL($context) . '\'';
        }

        return (int) \Db::getInstance()->getValue($sql);
    }

    /**
     * Delete old logs.
     *
     * @param int $daysOld Delete logs older than X days
     *
     * @return bool
     */
    public static function deleteOldLogs(int $daysOld = 30): bool
    {
        $sql = 'DELETE FROM `' . _DB_PREFIX_ . self::$definition['table'] . '`
                WHERE date_add < DATE_SUB(NOW(), INTERVAL ' . (int) $daysOld . ' DAY)';

        return \Db::getInstance()->execute($sql);
    }

    /**
     * Clear all logs.
     *
     * @return bool
     */
    public static function clearAll(): bool
    {
        return \Db::getInstance()->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . self::$definition['table'] . '`');
    }
}
