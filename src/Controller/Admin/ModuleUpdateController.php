<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Itrblueboost\Service\ModuleUpdater;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for module auto-update from GitHub.
 */
class ModuleUpdateController extends FrameworkBundleAdminController
{
    /**
     * @var ModuleUpdater
     */
    private $moduleUpdater;

    public function __construct(ModuleUpdater $moduleUpdater)
    {
        $this->moduleUpdater = $moduleUpdater;
    }

    /**
     * Check for available updates (returns cached data if fresh).
     *
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @return JsonResponse
     */
    public function checkAction(): JsonResponse
    {
        try {
            $info = $this->moduleUpdater->checkForUpdate();

            return new JsonResponse([
                'success' => true,
                'data' => $info,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Perform the module update.
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))")
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function performAction(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('itrblueboost_update', $request->request->get('_token'))) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Invalid CSRF token.',
            ], 403);
        }

        $zipUrl = $request->request->get('zipUrl');
        if (empty($zipUrl)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Missing ZIP URL.',
            ], 400);
        }

        // Step 1: Download and extract — failure here means nothing was changed
        try {
            $zipPath = $this->moduleUpdater->downloadRelease($zipUrl);
            $this->moduleUpdater->extractAndReplace($zipPath);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Download/extract failed: ' . $e->getMessage(),
            ], 500);
        }

        // Step 2: Run upgrade scripts — files are already deployed at this point,
        // so even if this fails the module is updated on disk.
        // PS core runUpgradeModule() may throw TypeError on PHP 8 (count(null)).
        $upgradeResult = ['success' => true, 'version' => '', 'upgradedFrom' => null];
        $upgradeWarning = null;

        try {
            $upgradeResult = $this->moduleUpdater->runUpgrade();
        } catch (\Throwable $e) {
            $upgradeWarning = 'Upgrade scripts skipped: ' . $e->getMessage();
            $this->moduleUpdater->clearCache();
        }

        return new JsonResponse([
            'success' => true,
            'data' => $upgradeResult,
            'warning' => $upgradeWarning,
        ]);
    }
}
