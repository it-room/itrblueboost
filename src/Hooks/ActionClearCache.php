<?php

declare(strict_types=1);

namespace Itrblueboost\Hooks;

use Itrblueboost;
use Itrblueboost\Service\ApiLogger;
use Itrblueboost\Service\FaqApiService;
use Itrblueboost\Service\ModuleUpdater;

/**
 * Hook handler for actionClearCache.
 *
 * Clears module caches (FAQ file cache + update check cache)
 * when PrestaShop cache is cleared from the back-office.
 */
class ActionClearCache
{
    /** @var Itrblueboost */
    private $module;

    public function __construct(Itrblueboost $module)
    {
        $this->module = $module;
    }

    /**
     * Execute the hook logic.
     *
     * @param array<string, mixed> $params Hook parameters
     */
    public function execute(array $params): void
    {
        $faqService = new FaqApiService(new ApiLogger());
        $faqService->clearCache();

        $moduleUpdater = new ModuleUpdater();
        $moduleUpdater->clearCache();
    }
}
