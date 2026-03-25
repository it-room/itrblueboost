<?php

declare(strict_types=1);

namespace Itrblueboost\Hooks;

use Configuration;
use Itrblueboost;
use Itrblueboost\Service\FaqApiService;
use PrestaShop\PrestaShop\Core\Product\ProductExtraContent;

/**
 * Hook handler for displayProductExtraContent.
 *
 * Fetches product FAQs from the external API and renders them
 * in the product page extra content section.
 */
class DisplayProductExtraContent
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
     *
     * @return array<int, ProductExtraContent>
     */
    public function execute(array $params): array
    {
        $idProduct = $this->extractProductId($params);

        if ($idProduct <= 0) {
            return [];
        }

        $langIso = \Context::getContext()->language->iso_code;
        $faqs = $this->faqApiService->getProductFaqs($idProduct, $langIso);

        if (empty($faqs)) {
            return [];
        }

        \Context::getContext()->smarty->assign([
            'faqs' => $faqs,
            'bootstrap_version' => Configuration::get(Itrblueboost::CONFIG_BOOTSTRAP_VERSION) ?: 'bootstrap5',
        ]);

        $extraContent = new ProductExtraContent();
        $extraContent->setTitle($this->module->trans('FAQ', [], 'Modules.Itrblueboost.Shop'));
        $extraContent->setContent(
            $this->module->fetch('module:itrblueboost/views/templates/hook/product_faq.tpl')
        );

        return [$extraContent];
    }

    private function extractProductId(array $params): int
    {
        $product = $params['product'] ?? null;

        if (!$product) {
            return 0;
        }

        if (\Validate::isLoadedObject($product)) {
            return (int) $product->id;
        }

        if (is_array($product) && !empty($product['id_product'])) {
            return (int) $product['id_product'];
        }

        return 0;
    }
}
