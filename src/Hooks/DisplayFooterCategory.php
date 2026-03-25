<?php

declare(strict_types=1);

namespace Itrblueboost\Hooks;

use CategoryController;
use Configuration;
use Itrblueboost;
use Itrblueboost\Service\FaqApiService;
use Tools;

/**
 * Hook handler for displayFooterCategory.
 *
 * Fetches category FAQs from the external API and renders them
 * in the category page footer.
 */
class DisplayFooterCategory
{
    /** @var Itrblueboost */
    private $module;

    /** @var FaqApiService */
    private $faqApiService;

    public function __construct(Itrblueboost $module, FaqApiService $faqApiService)
    {
        $this->module = $module;
        $this->faqApiService = $faqApiService;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(array $params): string
    {
        $idCategory = $this->extractCategoryId($params);

        if ($idCategory <= 0) {
            return '';
        }

        $langIso = \Context::getContext()->language->iso_code;
        $faqs = $this->faqApiService->getCategoryFaqs($idCategory, $langIso);

        if (empty($faqs)) {
            return '';
        }

        \Context::getContext()->smarty->assign([
            'faqs' => $faqs,
            'bootstrap_version' => Configuration::get(Itrblueboost::CONFIG_BOOTSTRAP_VERSION) ?: 'bootstrap5',
        ]);

        return $this->module->fetch('module:itrblueboost/views/templates/hook/category_faq.tpl');
    }

    private function extractCategoryId(array $params): int
    {
        if (!empty($params['category'])) {
            $category = $params['category'];

            if (is_object($category)) {
                return (int) $category->id;
            }

            if (is_array($category) && !empty($category['id_category'])) {
                return (int) $category['id_category'];
            }
        }

        $context = \Context::getContext();

        if ($context->controller instanceof CategoryController) {
            $category = $context->controller->getCategory();
            if ($category) {
                return (int) $category->id;
            }
        }

        return (int) Tools::getValue('id_category');
    }
}
