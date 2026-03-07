<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Front controller for webhook endpoints.
 *
 * Accepts POST requests from the BlueBoost API (api.blueboost.fr)
 * to synchronize data like credits.
 *
 * URL: /module/itrblueboost/webhook
 */
class ItrblueboostWebhookModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $auth = false;

    /** @var bool */
    public $ajax = true;

    public function initContent(): void
    {
        parent::initContent();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['error' => 'Method not allowed'], 405);
            return;
        }

        if (!$this->authenticateApiKey()) {
            $this->sendJsonResponse(['error' => 'Unauthorized'], 401);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if (!is_array($body) || empty($body['action'])) {
            $this->sendJsonResponse(['error' => 'Invalid request body, "action" is required'], 400);
            return;
        }

        $this->dispatchAction($body['action'], $body);
    }

    /**
     * Validate the X-API-Key header against the stored API key.
     */
    private function authenticateApiKey(): bool
    {
        $apiKey = $this->getApiKeyFromHeaders();

        if (empty($apiKey)) {
            return false;
        }

        $storedKey = Configuration::get(Itrblueboost::CONFIG_API_KEY);

        return !empty($storedKey) && hash_equals($storedKey, $apiKey);
    }

    /**
     * Extract API key from request headers.
     */
    private function getApiKeyFromHeaders(): string
    {
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return (string) $_SERVER['HTTP_X_API_KEY'];
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'x-api-key') {
                    return (string) $value;
                }
            }
        }

        return '';
    }

    /**
     * Dispatch to the appropriate handler based on action.
     *
     * @param string $action Action name
     * @param array<string, mixed> $data Request body
     */
    private function dispatchAction(string $action, array $data): void
    {
        switch ($action) {
            case 'sync_credits':
                $this->handleSyncCredits($data);
                break;
            case 'sync_services':
                $this->handleSyncServices($data);
                break;
            default:
                $this->sendJsonResponse(['error' => 'Unknown action: ' . $action], 400);
        }
    }

    /**
     * Handle sync_credits action: set the absolute credit total.
     *
     * @param array<string, mixed> $data Request data
     */
    private function handleSyncCredits(array $data): void
    {
        if (!isset($data['credits_total']) || !is_numeric($data['credits_total'])) {
            $this->sendJsonResponse(['error' => '"credits_total" is required and must be numeric'], 400);
            return;
        }

        $creditsTotal = (int) $data['credits_total'];

        if ($creditsTotal < 0) {
            $this->sendJsonResponse(['error' => '"credits_total" must be >= 0'], 400);
            return;
        }

        $previousTotal = (int) Configuration::get(Itrblueboost::CONFIG_CREDITS_REMAINING);
        Configuration::updateGlobalValue(Itrblueboost::CONFIG_CREDITS_REMAINING, $creditsTotal);

        $diff = $creditsTotal - $previousTotal;
        $details = !empty($data['reason']) ? (string) $data['reason'] : 'API webhook credit sync';

        if ($diff > 0) {
            \Itrblueboost\Entity\CreditHistory::log(
                'credit_topup',
                $diff,
                $creditsTotal,
                null,
                null,
                $details
            );
        }

        $this->sendJsonResponse([
            'success' => true,
            'credits_previous' => $previousTotal,
            'credits_total' => $creditsTotal,
        ]);
    }

    /**
     * Handle sync_services action: set active services state.
     *
     * @param array<string, mixed> $data Request data
     */
    private function handleSyncServices(array $data): void
    {
        if (!isset($data['services']) || !is_array($data['services'])) {
            $this->sendJsonResponse(['error' => '"services" is required and must be an object'], 400);
            return;
        }

        $serviceMap = [
            'faq' => Itrblueboost::CONFIG_SERVICE_FAQ,
            'image' => Itrblueboost::CONFIG_SERVICE_IMAGE,
            'category_faq' => Itrblueboost::CONFIG_SERVICE_CATEGORY_FAQ,
            'content' => Itrblueboost::CONFIG_SERVICE_CONTENT,
        ];

        $updated = [];

        foreach ($data['services'] as $serviceCode => $active) {
            if (!isset($serviceMap[$serviceCode])) {
                continue;
            }

            $value = $active ? 1 : 0;
            Configuration::updateGlobalValue($serviceMap[$serviceCode], $value);
            $updated[$serviceCode] = (bool) $value;
        }

        $this->sendJsonResponse([
            'success' => true,
            'services' => $updated,
        ]);
    }

    /**
     * Send a JSON response and terminate.
     *
     * @param array<string, mixed> $data Response data
     * @param int $httpCode HTTP status code
     */
    private function sendJsonResponse(array $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
