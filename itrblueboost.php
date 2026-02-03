<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Itrblueboost\Entity\CategoryFaq;
use Itrblueboost\Entity\ProductFaq;
use Itrblueboost\Entity\ProductImage;
use Itrblueboost\Install\Installer;
use PrestaShop\PrestaShop\Core\Product\ProductExtraContent;

/**
 * Module Itrblueboost - ITROOM API Integration.
 *
 * Integration module with ITROOM API for data synchronization.
 * Compatible with PrestaShop 8.x only.
 */
class Itrblueboost extends Module
{
    public const CONFIG_API_KEY = 'ITRBLUEBOOST_API_KEY';
    public const CONFIG_SERVICE_FAQ = 'ITRBLUEBOOST_SERVICE_FAQ';
    public const CONFIG_SERVICE_IMAGE = 'ITRBLUEBOOST_SERVICE_IMAGE';
    public const CONFIG_SERVICE_CATEGORY_FAQ = 'ITRBLUEBOOST_SERVICE_CATEGORY_FAQ';

    public function __construct()
    {
        $this->name = 'itrblueboost';
        $this->tab = 'administration';
        $this->version = '1.4.4';
        $this->author = 'ITROOM';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.8.11',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('ITROOM API Integration', [], 'Modules.Itrblueboost.Admin');
        $this->description = $this->trans(
            'Integration module with ITROOM API for data synchronization.',
            [],
            'Modules.Itrblueboost.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall this module?',
            [],
            'Modules.Itrblueboost.Admin'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->getInstaller()->install()
            && $this->registerHook('actionAdminControllerSetMedia')
            && $this->registerHook('displayProductExtraContent')
            && $this->registerHook('actionProductDelete')
            && $this->registerHook('actionObjectImageDeleteAfter')
            && $this->registerHook('displayFooterCategory')
            && $this->registerHook('actionCategoryDelete')
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall(): bool
    {
        return $this->getInstaller()->uninstall() && parent::uninstall();
    }

    public function getContent(): void
    {
        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');
        $configUrl = $router->generate('itrblueboost_configuration');

        Tools::redirectAdmin($configUrl);
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    private function getInstaller(): Installer
    {
        return new Installer($this);
    }

    /**
     * Hook to load assets on admin pages.
     *
     * @param array<string, mixed> $params Hook parameters
     */
    public function hookActionAdminControllerSetMedia(array $params): void
    {
        $apiKey = Configuration::get(self::CONFIG_API_KEY);

        // Debug logging
        error_log('[ITRBLUEBOOST] hookActionAdminControllerSetMedia called');
        error_log('[ITRBLUEBOOST] API Key configured: ' . (!empty($apiKey) ? 'YES' : 'NO'));

        if (empty($apiKey)) {
            error_log('[ITRBLUEBOOST] No API key, returning');
            return;
        }

        $faqServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_FAQ);
        $imageServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_IMAGE);
        $categoryFaqServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_CATEGORY_FAQ);

        error_log('[ITRBLUEBOOST] Services - FAQ: ' . ($faqServiceActive ? 'ON' : 'OFF') . ', Image: ' . ($imageServiceActive ? 'ON' : 'OFF') . ', CatFAQ: ' . ($categoryFaqServiceActive ? 'ON' : 'OFF'));

        if (!$faqServiceActive && !$imageServiceActive && !$categoryFaqServiceActive) {
            error_log('[ITRBLUEBOOST] No services active, returning');
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        error_log('[ITRBLUEBOOST] Request URI: ' . $requestUri);

        // Check if we're on product list page
        $isProductListPage = (strpos($requestUri, '/sell/catalog/products-v2') !== false
            || strpos($requestUri, '/sell/catalog/products') !== false)
            && strpos($requestUri, '/edit') === false
            && !preg_match('/\/products-v2\/\d+/', $requestUri)
            && !preg_match('/\/products\/\d+/', $requestUri);

        if ($isProductListPage && $faqServiceActive) {
            $this->loadProductListAssets();
            return;
        }

        // Check if we're on category list page
        $isCategoryListPage = strpos($requestUri, '/sell/catalog/categories') !== false
            && strpos($requestUri, '/edit') === false
            && !preg_match('/\/categories\/\d+/', $requestUri);

        if ($isCategoryListPage && $categoryFaqServiceActive) {
            $this->loadCategoryListAssets();
            return;
        }

        // Check if we're on category edit page
        $isCategoryPage = strpos($requestUri, '/sell/catalog/categories/') !== false;

        if ($isCategoryPage && $categoryFaqServiceActive) {
            $idCategory = $this->getCategoryIdFromUrl($requestUri);

            if ($idCategory > 0) {
                /** @var \Symfony\Component\Routing\RouterInterface $router */
                $router = $this->get('router');

                $faqCount = CategoryFaq::countByCategory($idCategory);

                Media::addJsDef([
                    'itrblueboostCategoryFaqCount' => (int) $faqCount,
                    'itrblueboostCategoryFaqUrl' => $router->generate('itrblueboost_admin_category_faq_index', [
                        'id_category' => $idCategory,
                    ]),
                ]);

                $this->context->controller->addJS($this->_path . 'views/js/admin-category-toolbar.js?v=' . $this->version);
                return;
            }
        }

        // Check if we're on product edit page (PS8 or PS1.7 legacy)
        $isProductPage = strpos($requestUri, '/sell/catalog/products/') !== false
            || strpos($requestUri, '/sell/catalog/products-v2/') !== false;

        // PS 1.7.x legacy product page
        $isLegacyProductPage = strpos($requestUri, 'controller=AdminProducts') !== false
            && (strpos($requestUri, 'updateproduct') !== false || strpos($requestUri, 'addproduct') !== false);

        error_log('[ITRBLUEBOOST] isProductPage: ' . ($isProductPage ? 'YES' : 'NO') . ', isLegacyProductPage: ' . ($isLegacyProductPage ? 'YES' : 'NO'));

        if (!$isProductPage && !$isLegacyProductPage) {
            error_log('[ITRBLUEBOOST] Not a product page, returning');
            return;
        }

        $idProduct = $this->getProductIdFromUrl($requestUri);
        error_log('[ITRBLUEBOOST] Product ID extracted: ' . $idProduct);

        if ($idProduct <= 0) {
            error_log('[ITRBLUEBOOST] Invalid product ID, returning');
            return;
        }

        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');

        $jsDef = [];

        if ($faqServiceActive) {
            $faqCount = ProductFaq::countByProduct($idProduct);
            $jsDef['itrblueboostFaqCount'] = (int) $faqCount;
            $jsDef['itrblueboostFaqUrl'] = $router->generate('itrblueboost_admin_product_faq_index', [
                'id_product' => $idProduct,
            ]);
        }

        if ($imageServiceActive) {
            $jsDef['itrblueboostImageUrl'] = $router->generate('itrblueboost_admin_product_image_index', [
                'id_product' => $idProduct,
            ]);
        }

        Media::addJsDef($jsDef);

        $this->context->controller->addJS($this->_path . 'views/js/admin-product-toolbar.js?v=' . $this->version);

        // Load version-specific CSS
        if ($this->isPrestaShop8()) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin-product-buttons-ps8.css?v=' . $this->version);
        } else {
            $this->context->controller->addCSS($this->_path . 'views/css/admin-product-buttons-ps17.css?v=' . $this->version);
        }
    }

    /**
     * Check if current PrestaShop version is 8.x or higher.
     *
     * @return bool
     */
    private function isPrestaShop8(): bool
    {
        return version_compare(_PS_VERSION_, '8.0.0', '>=');
    }

    /**
     * Load assets for product list page (bulk actions).
     */
    private function loadProductListAssets(): void
    {
        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');

        Media::addJsDef([
            'itrblueboostBulkFaqPromptsUrl' => $router->generate('itrblueboost_admin_product_faq_prompts'),
            'itrblueboostBulkFaqGenerateUrl' => $router->generate('itrblueboost_admin_product_faq_bulk_generate'),
            'itrblueboostBulkFaqLabel' => $this->trans('Generate FAQ (AI)', [], 'Modules.Itrblueboost.Admin'),
        ]);

        $this->context->controller->addJS($this->_path . 'views/js/admin-product-list-bulk.js?v=' . $this->version);
        $this->context->controller->addCSS($this->_path . 'views/css/admin-product-list-bulk.css?v=' . $this->version);
    }

    /**
     * Load assets for category list page (bulk actions).
     */
    private function loadCategoryListAssets(): void
    {
        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');

        Media::addJsDef([
            'itrblueboostBulkCategoryFaqPromptsUrl' => $router->generate('itrblueboost_admin_category_faq_prompts'),
            'itrblueboostBulkCategoryFaqGenerateUrl' => $router->generate('itrblueboost_admin_category_faq_bulk_generate'),
            'itrblueboostBulkCategoryFaqLabel' => $this->trans('Generate FAQ (AI)', [], 'Modules.Itrblueboost.Admin'),
        ]);

        $this->context->controller->addJS($this->_path . 'views/js/admin-category-list-bulk.js?v=' . $this->version);
        $this->context->controller->addCSS($this->_path . 'views/css/admin-product-list-bulk.css?v=' . $this->version);
    }

    /**
     * Extract product ID from URL.
     *
     * @param string $url Current URL
     *
     * @return int Product ID or 0 if not found
     */
    private function getProductIdFromUrl(string $url): int
    {
        if (preg_match('/\/products-v2\/(\d+)/', $url, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/\/products\/(\d+)/', $url, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/[?&]id_product=(\d+)/', $url, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Extract category ID from URL.
     *
     * @param string $url Current URL
     *
     * @return int Category ID or 0 if not found
     */
    private function getCategoryIdFromUrl(string $url): int
    {
        if (preg_match('/\/categories\/(\d+)/', $url, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/[?&]id_category=(\d+)/', $url, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/[?&]categoryId=(\d+)/', $url, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Hook to display FAQ content on product page (Front-Office).
     *
     * @param array<string, mixed> $params Hook parameters
     *
     * @return array<int, ProductExtraContent>
     */
    public function hookDisplayProductExtraContent(array $params): array
    {
        $product = $params['product'] ?? null;

        if (!$product) {
            return [];
        }

        if (is_object($product)) {
            $idProduct = (int) $product->id;
        } elseif (is_array($product) && !empty($product['id_product'])) {
            $idProduct = (int) $product['id_product'];
        } else {
            return [];
        }

        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        $faqs = ProductFaq::getByProduct($idProduct, $idLang, $idShop, true);

        if (empty($faqs)) {
            return [];
        }

        $this->smarty->assign([
            'faqs' => $faqs,
        ]);

        $extraContent = new ProductExtraContent();
        $extraContent->setTitle($this->trans('FAQ', [], 'Modules.Itrblueboost.Shop'));
        $extraContent->setContent($this->fetch('module:itrblueboost/views/templates/hook/product_faq.tpl'));

        return [$extraContent];
    }

    /**
     * Hook to delete FAQs and images when a product is deleted.
     *
     * @param array<string, mixed> $params Hook parameters
     */
    public function hookActionProductDelete(array $params): void
    {
        $idProduct = (int) ($params['id_product'] ?? 0);

        if ($idProduct > 0) {
            ProductFaq::deleteByProduct($idProduct);
            ProductImage::deleteByProduct($idProduct);
        }
    }

    /**
     * Hook to update AI images when a PrestaShop image is deleted.
     *
     * @param array<string, mixed> $params Hook parameters
     */
    public function hookActionObjectImageDeleteAfter(array $params): void
    {
        $image = $params['object'] ?? null;

        if (!$image || !$image->id) {
            return;
        }

        $idImage = (int) $image->id;

        $sql = 'SELECT id_itrblueboost_product_image FROM `' . _DB_PREFIX_ . 'itrblueboost_product_image`
                WHERE id_image = ' . $idImage;

        $result = \Db::getInstance()->getValue($sql);

        if ($result) {
            $productImage = new ProductImage((int) $result);
            if ($productImage->id) {
                $productImage->delete();
            }
        }
    }

    /**
     * Hook to display FAQ content in category page footer (Front-Office).
     *
     * @param array<string, mixed> $params Hook parameters
     *
     * @return string HTML content
     */
    public function hookDisplayFooterCategory(array $params): string
    {
        $categoryFaqServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_CATEGORY_FAQ);

        if (!$categoryFaqServiceActive) {
            return '';
        }

        $idCategory = 0;

        // Try to get category from params
        if (!empty($params['category'])) {
            $category = $params['category'];
            if (is_object($category)) {
                $idCategory = (int) $category->id;
            } elseif (is_array($category) && !empty($category['id_category'])) {
                $idCategory = (int) $category['id_category'];
            }
        }

        // Fallback: get from controller
        if ($idCategory === 0 && $this->context->controller instanceof CategoryController) {
            $category = $this->context->controller->getCategory();
            if ($category) {
                $idCategory = (int) $category->id;
            }
        }

        // Fallback: get from URL parameter
        if ($idCategory === 0) {
            $idCategory = (int) Tools::getValue('id_category');
        }

        if ($idCategory <= 0) {
            return '';
        }

        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        $faqs = CategoryFaq::getByCategory($idCategory, $idLang, $idShop, true);

        if (empty($faqs)) {
            return '';
        }

        $this->smarty->assign([
            'faqs' => $faqs,
        ]);

        return $this->fetch('module:itrblueboost/views/templates/hook/category_faq.tpl');
    }

    /**
     * Hook to delete FAQs when a category is deleted.
     *
     * @param array<string, mixed> $params Hook parameters
     */
    public function hookActionCategoryDelete(array $params): void
    {
        $category = $params['category'] ?? $params['object'] ?? null;

        if (!$category) {
            return;
        }

        $idCategory = 0;

        if (is_object($category) && isset($category->id)) {
            $idCategory = (int) $category->id;
        } elseif (is_array($category) && !empty($category['id_category'])) {
            $idCategory = (int) $category['id_category'];
        }

        if ($idCategory > 0) {
            CategoryFaq::deleteByCategory($idCategory);
        }
    }

    /**
     * Hook to display credits in admin header.
     *
     * @param array<string, mixed> $params Hook parameters
     *
     * @return string
     */
    public function hookDisplayBackOfficeHeader(array $params): string
    {
        $apiKey = Configuration::get(self::CONFIG_API_KEY);

        if (empty($apiKey)) {
            return '';
        }

        // Use cache to avoid too many API calls (cache for 5 minutes)
        $cacheKey = 'itrblueboost_credits_' . md5($apiKey);
        $credits = null;

        if (Cache::isStored($cacheKey)) {
            $credits = Cache::retrieve($cacheKey);
        } else {
            $apiService = new \Itrblueboost\Service\ApiService();
            $accountInfo = $apiService->getAccountInfo();

            if (isset($accountInfo['success']) && $accountInfo['success'] && isset($accountInfo['client']['credits'])) {
                $credits = (int) $accountInfo['client']['credits'];
                Cache::store($cacheKey, $credits);
            }
        }

        if ($credits === null) {
            return '';
        }

        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $this->get('router');
        $configUrl = $router->generate('itrblueboost_configuration');

        $html = '
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
        <style>
            .itrblueboost-credits-badge {
                display: inline-flex !important;
                align-items: center !important;
                gap: 6px;
                background: linear-gradient(135deg, #70d99f 0%, #4caf50 100%) !important;
                color: #fff !important;
                padding: 6px 14px !important;
                border-radius: 20px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                text-decoration: none !important;
                margin-left: 10px;
                transition: all 0.2s ease;
                box-shadow: 0 2px 4px rgba(76, 175, 80, 0.3);
                vertical-align: middle;
                line-height: 1.4;
                font-family: "Open Sans", Arial, Helvetica, sans-serif;
            }
            .itrblueboost-credits-badge:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(76, 175, 80, 0.4);
                color: #fff !important;
                text-decoration: none !important;
            }
            .itrblueboost-credits-badge .material-icons {
                font-size: 16px;
            }
            .itrblueboost-credits-badge .credits-count {
                font-weight: 700;
            }
        </style>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Modern pages: new Symfony header
                var shopLink = document.querySelector("#header-shop-list-container, .shop-list, a[href*=\'/\'][target=\'_blank\'].header-link, .header_shop_name");

                if (!shopLink) {
                    // Legacy pages: old header structure
                    var allLinks = document.querySelectorAll("#header_infos a, .header-right a, #header_quick a, #header_employee_box a");
                    for (var i = 0; i < allLinks.length; i++) {
                        if (allLinks[i].getAttribute("target") === "_blank" || allLinks[i].textContent.toLowerCase().includes("boutique")) {
                            shopLink = allLinks[i];
                            break;
                        }
                    }
                }

                var badge = document.createElement("a");
                badge.href = "' . $configUrl . '";
                badge.className = "itrblueboost-credits-badge";
                badge.innerHTML = \'<i class="material-icons">toll</i><span class="credits-count">' . $credits . '</span> crédits\';
                badge.title = "Crédits ITROOM restants";

                // Modern page: Symfony header detected
                var isModern = document.querySelector("#header-shop-list-container, .component-name-wrapper, .header-right .shop-list");
                if (isModern) {
                    if (shopLink && shopLink.parentElement) {
                        shopLink.parentElement.insertBefore(badge, shopLink.nextSibling);
                    } else {
                        var header = document.querySelector(".header-right, header .navbar");
                        if (header) {
                            header.appendChild(badge);
                        }
                    }
                } else {
                    // Legacy page: insert after the "Voir ma boutique" li
                    var shopnameLi = document.querySelector("li.shopname");
                    if (shopnameLi && shopnameLi.parentElement) {
                        var li = document.createElement("li");
                        li.className = "shopname";
                        li.style.marginTop = "5px";
                        li.setAttribute("data-mobile", "true");
                        li.setAttribute("data-from", "header-list");
                        li.setAttribute("data-target", "menu");
                        li.appendChild(badge);
                        shopnameLi.parentElement.insertBefore(li, shopnameLi.nextSibling);
                    }
                }
            });
        </script>';

        return $html;
    }
}
