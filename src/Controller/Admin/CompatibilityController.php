<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for module compatibility settings page.
 */
class CompatibilityController extends FrameworkBundleAdminController
{
    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    public function __construct(FormHandlerInterface $formHandler)
    {
        $this->formHandler = $formHandler;
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

                return $this->redirectToRoute('itrblueboost_compatibility');
            }

            $this->flashErrors($errors);
        }

        return $this->render('@Modules/itrblueboost/views/templates/admin/compatibility/index.html.twig', [
            'form' => $form->createView(),
            'help_link' => false,
            'enableSidebar' => true,
            'layoutHeaderToolbarBtn' => [],
            'layoutTitle' => $this->trans('Compatibility', 'Modules.Itrblueboost.Admin'),
        ]);
    }
}
