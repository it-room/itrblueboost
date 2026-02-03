<?php

declare(strict_types=1);

namespace Itrblueboost\Controller\Admin;

use Context;
use Db;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for listing all generated FAQs.
 */
class GeneratedFaqsController extends FrameworkBundleAdminController
{
    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function indexAction(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $typeFilter = $request->query->get('type', '');
        $statusFilter = $request->query->get('status', '');

        $productFaqs = [];
        $categoryFaqs = [];
        $totalFaqs = 0;

        $idLang = (int) Context::getContext()->language->id;
        $idShop = (int) Context::getContext()->shop->id;

        // Product FAQs
        if ($typeFilter === '' || $typeFilter === 'product') {
            $sql = 'SELECT pf.*, pfl.question, pfl.answer, p.id_product, pl.name as entity_name, "product" as faq_type
                    FROM `' . _DB_PREFIX_ . 'itrblueboost_product_faq` pf
                    LEFT JOIN `' . _DB_PREFIX_ . 'itrblueboost_product_faq_lang` pfl
                        ON pfl.id_itrblueboost_product_faq = pf.id_itrblueboost_product_faq
                        AND pfl.id_lang = ' . $idLang . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.id_product = pf.id_product
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product
                        AND pl.id_lang = ' . $idLang . '
                        AND pl.id_shop = ' . $idShop . '
                    ORDER BY pf.date_add DESC';

            $productFaqs = Db::getInstance()->executeS($sql) ?: [];
        }

        // Category FAQs
        if ($typeFilter === '' || $typeFilter === 'category') {
            $whereStatus = '';
            if ($statusFilter && in_array($statusFilter, ['pending', 'accepted', 'rejected'], true)) {
                $whereStatus = ' WHERE cf.status = "' . pSQL($statusFilter) . '"';
            }

            $sql = 'SELECT cf.*, cfl.question, cfl.answer, c.id_category, cl.name as entity_name, "category" as faq_type
                    FROM `' . _DB_PREFIX_ . 'itrblueboost_category_faq` cf
                    LEFT JOIN `' . _DB_PREFIX_ . 'itrblueboost_category_faq_lang` cfl
                        ON cfl.id_itrblueboost_category_faq = cf.id_itrblueboost_category_faq
                        AND cfl.id_lang = ' . $idLang . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'category` c ON c.id_category = cf.id_category
                    LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category
                        AND cl.id_lang = ' . $idLang . '
                        AND cl.id_shop = ' . $idShop . '
                    ' . $whereStatus . '
                    ORDER BY cf.date_add DESC';

            $categoryFaqs = Db::getInstance()->executeS($sql) ?: [];
        }

        // Merge and sort by date
        $allFaqs = array_merge($productFaqs, $categoryFaqs);
        usort($allFaqs, function ($a, $b) {
            return strtotime($b['date_add']) - strtotime($a['date_add']);
        });

        $totalFaqs = count($allFaqs);
        $totalPages = (int) ceil($totalFaqs / $limit);

        // Paginate
        $faqs = array_slice($allFaqs, $offset, $limit);

        return $this->render('@Modules/itrblueboost/views/templates/admin/generated_faqs/index.html.twig', [
            'faqs' => $faqs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalFaqs' => $totalFaqs,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'layoutTitle' => $this->trans('Generated FAQs', 'Modules.Itrblueboost.Admin'),
        ]);
    }
}
