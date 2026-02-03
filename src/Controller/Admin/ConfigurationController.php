<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Itrblueboost\Entity\CreditHistory;
use Itrblueboost\Service\ApiService;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for module configuration page.
 */
class ConfigurationController extends FrameworkBundleAdminController
{
    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * @var ApiService
     */
    private $apiService;

    public function __construct(FormHandlerInterface $formHandler, ApiService $apiService)
    {
        $this->formHandler = $formHandler;
        $this->apiService = $apiService;
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function indexAction(Request $request): Response
    {
        $form = $this->formHandler->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $errors = $this->formHandler->save($form->getData());

            if (empty($errors)) {
                $this->addFlash(
                    'success',
                    $this->trans('Configuration saved successfully.', 'Modules.Itrblueboost.Admin')
                );

                return $this->redirectToRoute('itrblueboost_configuration');
            }

            $this->flashErrors($errors);
        }

        $accountInfo = null;
        if ($this->apiService->hasApiKey()) {
            $accountInfo = $this->apiService->getAccountInfo();
        }

        // Get credit history data
        $creditHistory = CreditHistory::getRecentHistory(10);
        $creditStats = CreditHistory::getStatsByService(30);
        $dailyConsumption = CreditHistory::getDailyConsumption(30);
        $totalCreditsUsed30Days = CreditHistory::getTotalCreditsUsed(30);
        $totalCreditsUsedAllTime = CreditHistory::getTotalCreditsUsed(0);

        return $this->render('@Modules/itrblueboost/views/templates/admin/configuration.html.twig', [
            'form' => $form->createView(),
            'accountInfo' => $accountInfo,
            'creditHistory' => $creditHistory,
            'creditStats' => $creditStats,
            'dailyConsumption' => $dailyConsumption,
            'totalCreditsUsed30Days' => $totalCreditsUsed30Days,
            'totalCreditsUsedAllTime' => $totalCreditsUsedAllTime,
            'help_link' => false,
            'enableSidebar' => true,
            'layoutHeaderToolbarBtn' => [],
            'layoutTitle' => $this->trans('ITROOM API Configuration', 'Modules.Itrblueboost.Admin'),
        ]);
    }
}
