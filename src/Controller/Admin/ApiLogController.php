<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Itrblueboost\Entity\ApiLog;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for API logs management.
 */
class ApiLogController extends FrameworkBundleAdminController
{
    private const LOGS_PER_PAGE = 50;

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function indexAction(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $context = $request->query->get('context', '');

        $offset = ($page - 1) * self::LOGS_PER_PAGE;
        $contextFilter = !empty($context) ? $context : null;

        $logs = ApiLog::getRecentLogs(self::LOGS_PER_PAGE, $offset, $contextFilter);
        $totalLogs = ApiLog::countLogs($contextFilter);
        $totalPages = max(1, (int) ceil($totalLogs / self::LOGS_PER_PAGE));

        // Get distinct contexts for filter dropdown
        $contexts = $this->getDistinctContexts();

        return $this->render('@Modules/itrblueboost/views/templates/admin/api_log/index.html.twig', [
            'logs' => $logs,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_logs' => $totalLogs,
            'context_filter' => $context,
            'contexts' => $contexts,
            'layoutTitle' => $this->trans('API Logs', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function viewAction(Request $request, int $logId): Response
    {
        $log = new ApiLog($logId);

        if (!$log->id) {
            $this->addFlash('error', $this->trans('Log not found.', 'Modules.Itrblueboost.Admin'));
            return $this->redirectToRoute('itrblueboost_admin_api_log_index');
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/api_log/view.html.twig', [
            'log' => $log,
            'layoutTitle' => $this->trans('API Log Details', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function clearAction(Request $request): JsonResponse
    {
        $result = ApiLog::clearAll();

        return new JsonResponse([
            'success' => $result,
            'message' => $result
                ? $this->trans('All logs cleared.', 'Modules.Itrblueboost.Admin')
                : $this->trans('Error clearing logs.', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))")
     */
    public function deleteOldAction(Request $request): JsonResponse
    {
        $days = (int) $request->request->get('days', 30);
        $result = ApiLog::deleteOldLogs($days);

        return new JsonResponse([
            'success' => $result,
            'message' => $result
                ? sprintf($this->trans('Logs older than %d days deleted.', 'Modules.Itrblueboost.Admin'), $days)
                : $this->trans('Error deleting old logs.', 'Modules.Itrblueboost.Admin'),
        ]);
    }

    /**
     * Get distinct contexts from logs.
     *
     * @return array<string>
     */
    private function getDistinctContexts(): array
    {
        $sql = 'SELECT DISTINCT context FROM `' . _DB_PREFIX_ . 'itrblueboost_api_log`
                WHERE context IS NOT NULL AND context != \'\'
                ORDER BY context ASC';

        $results = \Db::getInstance()->executeS($sql);

        if (!$results) {
            return [];
        }

        return array_column($results, 'context');
    }
}
