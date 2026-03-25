<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Itrblueboost\Entity\CategoryContent;
use Itrblueboost\Entity\CategoryFaq;
use Itrblueboost\Entity\ProductContent;
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
    public const CONFIG_SERVICE_CONTENT = 'ITRBLUEBOOST_SERVICE_CONTENT';
    public const CONFIG_SERVICE_CATEGORY_CONTENT = 'ITRBLUEBOOST_SERVICE_CATEGORY_CONTENT';
    public const CONFIG_CREDITS_REMAINING = 'ITRBLUEBOOST_CREDITS_REMAINING';
    public const CONFIG_BOOTSTRAP_VERSION = 'ITRBLUEBOOST_BOOTSTRAP_VERSION';
    public const CONFIG_API_MODE = 'ITRBLUEBOOST_API_MODE';
    public const CONFIG_WEBSERVICE_KEY_ID = 'ITRBLUEBOOST_WEBSERVICE_KEY_ID';
    public const CONFIG_UPDATE_CACHE = 'ITRBLUEBOOST_UPDATE_CACHE';

    public const API_BASE_URL_PROD = 'https://api.blueboost.fr';
    public const API_BASE_URL_TEST = 'https://blueboost.itroom.fr';

    public function __construct()
    {
        $this->name = 'itrblueboost';
        $this->tab = 'administration';
        $this->version = '1.8.23';
        $this->author = 'ITROOM';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.8.2',
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
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionMicrodataProduct')
            && $this->registerHook('actionMicrodataProductList')
            && $this->registerHook('actionMicrodataProductListPreload');
    }

    public function uninstall(): bool
    {
        return $this->getInstaller()->uninstall() && parent::uninstall();
    }

    public function getContent(): void
    {
        try {
            /** @var \Symfony\Component\Routing\RouterInterface $router */
            $router = $this->get('router');
            $configUrl = $router->generate('itrblueboost_configuration');
        } catch (\Exception $e) {
            $configUrl = $this->context->link->getAdminLink('AdminItrblueboostConfiguration');
        }

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

        if (empty($apiKey)) {
            return;
        }

        $faqServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_FAQ);
        $imageServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_IMAGE);
        $categoryFaqServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_CATEGORY_FAQ);
        $contentServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_CONTENT);
        $categoryContentServiceActive = (bool) Configuration::get(self::CONFIG_SERVICE_CATEGORY_CONTENT);

        if (!$faqServiceActive && !$imageServiceActive && !$categoryFaqServiceActive && !$contentServiceActive && !$categoryContentServiceActive) {
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Check if we're on product list page
        // List URL: /products or /products/{offset}/{limit}/{orderBy}/{orderWay}
        // Edit URL: /products/{id} or /products/{id}/edit or /products/{id}/form
        $isProductListPage = (strpos($requestUri, '/sell/catalog/products-v2') !== false
            || strpos($requestUri, '/sell/catalog/products') !== false)
            && strpos($requestUri, '/edit') === false
            && !preg_match('/\/products-v2\/\d+/', $requestUri)
            && !preg_match('/\/products\/\d+(?:\/(?:edit|form))?(?:\?|#|$)/', $requestUri);

        if ($isProductListPage && ($faqServiceActive || $imageServiceActive || $contentServiceActive)) {
            $this->loadProductListAssets($faqServiceActive, $imageServiceActive, $contentServiceActive);
            return;
        }

        // Check if we're on category list page
        // List URL: /categories or /categories/{offset}/{limit}/{orderBy}/{orderWay}
        // Edit URL: /categories/{id} or /categories/{id}/edit
        $isCategoryListPage = strpos($requestUri, '/sell/catalog/categories') !== false
            && strpos($requestUri, '/edit') === false
            && !preg_match('/\/categories\/\d+(?:\/(?:edit|form))?(?:\?|#|$)/', $requestUri);

        if ($isCategoryListPage && ($categoryFaqServiceActive || $categoryContentServiceActive)) {
            $this->loadCategoryListAssets($categoryFaqServiceActive, $categoryContentServiceActive);
            return;
        }

        // Check if we're on category edit page (PS8+ Symfony or PS1.7 legacy)
        $isCategoryPage = strpos($requestUri, '/sell/catalog/categories/') !== false;

        // PS 1.7.x legacy category page
        $isLegacyCategoryPage = strpos($requestUri, 'controller=AdminCategories') !== false
            && (strpos($requestUri, 'updatecategory') !== false || strpos($requestUri, 'addcategory') !== false);

        if (($isCategoryPage || $isLegacyCategoryPage) && ($categoryFaqServiceActive || $categoryContentServiceActive)) {
            $idCategory = $this->getCategoryIdFromUrl($requestUri);

            if ($idCategory > 0) {
                $this->loadCategoryEditAssets(
                    $idCategory,
                    $categoryFaqServiceActive,
                    $categoryContentServiceActive
                );

                return;
            }
        }

        // Check if we're on product edit page (PS8 or PS1.7 legacy)
        $isProductPage = strpos($requestUri, '/sell/catalog/products/') !== false
            || strpos($requestUri, '/sell/catalog/products-v2/') !== false;

        // PS 1.7.x legacy product page
        $isLegacyProductPage = strpos($requestUri, 'controller=AdminProducts') !== false
            && (strpos($requestUri, 'updateproduct') !== false || strpos($requestUri, 'addproduct') !== false);

        if (!$isProductPage && !$isLegacyProductPage) {
            return;
        }

        $idProduct = $this->getProductIdFromUrl($requestUri);

        if ($idProduct <= 0) {
            return;
        }

        try {
            /** @var \Symfony\Component\Routing\RouterInterface $router */
            $router = $this->get('router');
        } catch (\Exception $e) {
            return;
        }

        $jsDef = [];

        if ($faqServiceActive) {
            try {
                $faqCount = ProductFaq::countByProduct($idProduct);
                $jsDef['itrblueboostFaqCount'] = (int) $faqCount;
                $jsDef['itrblueboostFaqUrl'] = $router->generate('itrblueboost_admin_product_faq_index', [
                    'id_product' => $idProduct,
                ]);
            } catch (\Exception $e) {
                // Route not yet cached
            }
        }

        if ($imageServiceActive) {
            try {
                $jsDef['itrblueboostImageUrl'] = $router->generate('itrblueboost_admin_product_image_index', [
                    'id_product' => $idProduct,
                ]);
            } catch (\Exception $e) {
                // Route not yet cached
            }
        }

        if ($contentServiceActive) {
            try {
                $jsDef['itrblueboostProductId'] = $idProduct;
                $jsDef['itrblueboostContentPromptsUrl'] = $router->generate('itrblueboost_admin_product_content_prompts');
                $jsDef['itrblueboostContentGenerateUrl'] = $router->generate('itrblueboost_admin_product_content_generate', [
                    'id_product' => $idProduct,
                ]);
                $jsDef['itrblueboostContentAcceptUrl'] = $router->generate('itrblueboost_admin_product_content_accept', [
                    'id_product' => $idProduct,
                    'contentId' => 0,
                ]);
                $jsDef['itrblueboostContentUrl'] = $router->generate('itrblueboost_admin_product_content_index', [
                    'id_product' => $idProduct,
                ]);
            } catch (\Exception $e) {
                // Route not yet cached
            }
        }

        if (!empty($jsDef)) {
            Media::addJsDef($jsDef);
        }

        $this->context->controller->addJS($this->_path . 'views/js/admin-product-toolbar.js?v=' . $this->version);

        if ($contentServiceActive && isset($jsDef['itrblueboostContentPromptsUrl'])) {
            Media::addJsDef([
                'itrblueboostContentTranslations' => [
                    'modalTitle' => $this->trans('Generate content with AI', [], 'Modules.Itrblueboost.Admin'),
                    'loadingPrompts' => $this->trans('Loading available prompts...', [], 'Modules.Itrblueboost.Admin'),
                    'selectPrompt' => $this->trans('Select a prompt to generate content:', [], 'Modules.Itrblueboost.Admin'),
                    'choosePrompt' => $this->trans('Choose a prompt', [], 'Modules.Itrblueboost.Admin'),
                    'close' => $this->trans('Close', [], 'Admin.Actions'),
                    'generate' => $this->trans('Generate', [], 'Modules.Itrblueboost.Admin'),
                    'generating' => $this->trans('Generating... This may take a few seconds.', [], 'Modules.Itrblueboost.Admin'),
                ],
            ]);
            $this->context->controller->addJS($this->_path . 'views/js/admin-content-inline.js?v=' . $this->version);
        }

        // Load unified product buttons CSS (PS1.7 + PS8)
        $this->context->controller->addCSS($this->_path . 'views/css/admin-product-buttons.css?v=' . $this->version);
    }

    /**
     * Load assets for product list page (bulk actions + count badges).
     *
     * @param bool $faqActive Whether FAQ service is active
     * @param bool $imageActive Whether Image service is active
     * @param bool $contentActive Whether Content service is active
     */
    private function loadProductListAssets(bool $faqActive, bool $imageActive, bool $contentActive): void
    {
        try {
            /** @var \Symfony\Component\Routing\RouterInterface $router */
            $router = $this->get('router');
        } catch (\Exception $e) {
            return;
        }

        // Count badges (FAQ / Images / Content)
        try {
            Media::addJsDef([
                'itrblueboostListCountsUrl' => $router->generate('itrblueboost_admin_product_list_counts'),
            ]);

            $this->context->controller->addJS($this->_path . 'views/js/admin-product-list-counts.js?v=' . $this->version);
        } catch (\Exception $e) {
            // Route not yet cached, skip count badges
        }

        // Load common bulk utilities (must be loaded before specific bulk scripts)
        if ($faqActive || $imageActive || $contentActive) {
            Media::addJsDef([
                'itrblueboostModalTranslations' => [
                    'loading' => $this->trans('Loading available prompts...', [], 'Modules.Itrblueboost.Admin'),
                    'close' => $this->trans('Close', [], 'Admin.Actions'),
                    'generate' => $this->trans('Generate', [], 'Modules.Itrblueboost.Admin'),
                    'choosePrompt' => $this->trans('Choose a prompt', [], 'Modules.Itrblueboost.Admin'),
                    'insufficientCredits' => $this->trans('Insufficient credits. Please recharge your credits to use AI generation.', [], 'Modules.Itrblueboost.Admin'),
                    'generating' => $this->trans('Generating...', [], 'Admin.Global'),
                    'selected' => $this->trans('selected', [], 'Modules.Itrblueboost.Admin'),
                    'includingWithFaqs' => $this->trans('including %count% with generated FAQs', [], 'Modules.Itrblueboost.Admin'),
                    'includingWithImages' => $this->trans('including %count% with generated images', [], 'Modules.Itrblueboost.Admin'),
                    'includingWithContents' => $this->trans('including %count% with generated contents', [], 'Modules.Itrblueboost.Admin'),
                ],
            ]);
            $this->context->controller->addJS($this->_path . 'views/js/admin-bulk-common.js?v=' . $this->version);
        }

        if ($faqActive) {
            try {
                Media::addJsDef([
                    'itrblueboostBulkFaqPromptsUrl' => $router->generate('itrblueboost_admin_product_faq_prompts'),
                    'itrblueboostBulkFaqGenerateUrl' => $router->generate('itrblueboost_admin_product_faq_bulk_generate'),
                    'itrblueboostBulkFaqLabel' => $this->trans('Generate FAQ (AI)', [], 'Modules.Itrblueboost.Admin'),
                ]);

                $this->context->controller->addJS($this->_path . 'views/js/admin-product-list-bulk.js?v=' . $this->version);
            } catch (\Exception $e) {
                // Route not yet cached, skip FAQ bulk assets
            }
        }

        if ($imageActive) {
            try {
                Media::addJsDef([
                    'itrblueboostBulkImagePromptsUrl' => $router->generate('itrblueboost_admin_product_image_prompts'),
                    'itrblueboostBulkImageGenerateUrl' => $router->generate('itrblueboost_admin_product_image_bulk_generate'),
                    'itrblueboostBulkImageJobStatusUrl' => $router->generate('itrblueboost_admin_product_image_job_status', ['jobId' => 0]),
                    'itrblueboostBulkImageProcessUrl' => $router->generate('itrblueboost_admin_product_image_bulk_process', ['jobId' => 0]),
                    'itrblueboostBulkImageLabel' => $this->trans('Generate Images (AI)', [], 'Modules.Itrblueboost.Admin'),
                ]);

                $this->context->controller->addJS($this->_path . 'views/js/admin-product-list-bulk-images.js?v=' . $this->version);
            } catch (\Exception $e) {
                // Route not yet cached, skip Image bulk assets
            }
        }

        if ($contentActive) {
            try {
                Media::addJsDef([
                    'itrblueboostBulkContentPromptsUrl' => $router->generate('itrblueboost_admin_product_content_prompts'),
                    'itrblueboostBulkContentGenerateUrl' => $router->generate('itrblueboost_admin_product_content_bulk_generate'),
                    'itrblueboostBulkContentLabel' => $this->trans('Generate Content (AI)', [], 'Modules.Itrblueboost.Admin'),
                ]);

                $this->context->controller->addJS($this->_path . 'views/js/admin-product-list-bulk-content.js?v=' . $this->version);
            } catch (\Exception $e) {
                // Route not yet cached, skip Content bulk assets
            }
        }

        $this->context->controller->addCSS($this->_path . 'views/css/admin-product-list-bulk.css?v=' . $this->version);
    }

    /**
     * Load assets for category list page (bulk actions + count badges).
     *
     * @param bool $faqActive Whether FAQ service is active
     * @param bool $contentActive Whether Content service is active
     */
    private function loadCategoryListAssets(bool $faqActive = true, bool $contentActive = false): void
    {
        try {
            /** @var \Symfony\Component\Routing\RouterInterface $router */
            $router = $this->get('router');

            Media::addJsDef([
                'itrblueboostCategoryListCountsUrl' => $router->generate('itrblueboost_admin_category_list_counts'),
                'itrblueboostModalTranslations' => [
                    'loading' => $this->trans('Loading available prompts...', [], 'Modules.Itrblueboost.Admin'),
                    'close' => $this->trans('Close', [], 'Admin.Actions'),
                    'generate' => $this->trans('Generate', [], 'Modules.Itrblueboost.Admin'),
                    'choosePrompt' => $this->trans('Choose a prompt', [], 'Modules.Itrblueboost.Admin'),
                    'insufficientCredits' => $this->trans('Insufficient credits. Please recharge your credits to use AI generation.', [], 'Modules.Itrblueboost.Admin'),
                    'generating' => $this->trans('Generating...', [], 'Admin.Global'),
                    'selected' => $this->trans('selected', [], 'Modules.Itrblueboost.Admin'),
                    'includingWithFaqs' => $this->trans('including %count% with generated FAQs', [], 'Modules.Itrblueboost.Admin'),
                    'includingWithContents' => $this->trans('including %count% with generated contents', [], 'Modules.Itrblueboost.Admin'),
                ],
            ]);

            $this->context->controller->addJS($this->_path . 'views/js/admin-bulk-common.js?v=' . $this->version);

            if ($faqActive) {
                Media::addJsDef([
                    'itrblueboostBulkCategoryFaqPromptsUrl' => $router->generate('itrblueboost_admin_category_faq_prompts'),
                    'itrblueboostBulkCategoryFaqGenerateUrl' => $router->generate('itrblueboost_admin_category_faq_bulk_generate'),
                    'itrblueboostBulkCategoryFaqLabel' => $this->trans('Generate FAQ (AI)', [], 'Modules.Itrblueboost.Admin'),
                ]);
                $this->context->controller->addJS($this->_path . 'views/js/admin-category-list-bulk.js?v=' . $this->version);
            }

            if ($contentActive) {
                Media::addJsDef([
                    'itrblueboostBulkCategoryContentPromptsUrl' => $router->generate('itrblueboost_admin_category_content_prompts'),
                    'itrblueboostBulkCategoryContentGenerateUrl' => $router->generate('itrblueboost_admin_category_content_bulk_generate'),
                    'itrblueboostBulkCategoryContentLabel' => $this->trans('Generate Content (AI)', [], 'Modules.Itrblueboost.Admin'),
                ]);
                $this->context->controller->addJS($this->_path . 'views/js/admin-category-list-bulk-content.js?v=' . $this->version);
            }

            $this->context->controller->addJS($this->_path . 'views/js/admin-category-list-counts.js?v=' . $this->version);
            $this->context->controller->addCSS($this->_path . 'views/css/admin-product-list-bulk.css?v=' . $this->version);
        } catch (\Exception $e) {
            // Route not yet cached, skip category bulk assets
        }
    }

    /**
     * Load JS/CSS assets for category edit page.
     *
     * Assets are loaded outside try/catch so the JS files are always
     * included even if route generation fails (e.g. cache not warmed).
     *
     * @param int  $idCategory              Category ID
     * @param bool $categoryFaqActive       Whether FAQ service is active
     * @param bool $categoryContentActive   Whether Content service is active
     */
    private function loadCategoryEditAssets(
        int $idCategory,
        bool $categoryFaqActive,
        bool $categoryContentActive
    ): void {
        // Always load toolbar JS - it checks for URL vars before injecting buttons
        $this->context->controller->addJS($this->_path . 'views/js/admin-category-toolbar.js?v=' . $this->version);
        $this->context->controller->addCSS($this->_path . 'views/css/admin-product-buttons.css?v=' . $this->version);

        try {
            /** @var \Symfony\Component\Routing\RouterInterface $router */
            $router = $this->get('router');
        } catch (\Exception $e) {
            return;
        }

        $jsDef = [];

        if ($categoryFaqActive) {
            try {
                $faqCount = CategoryFaq::countByCategory($idCategory);
                $jsDef['itrblueboostCategoryFaqCount'] = (int) $faqCount;
                $jsDef['itrblueboostCategoryFaqUrl'] = $router->generate('itrblueboost_admin_category_faq_index', [
                    'id_category' => $idCategory,
                ]);
            } catch (\Exception $e) {
                // Route not available
            }
        }

        if ($categoryContentActive) {
            try {
                $jsDef['itrblueboostCategoryContentPromptsUrl'] = $router->generate('itrblueboost_admin_category_content_prompts');
                $jsDef['itrblueboostCategoryContentGenerateUrl'] = $router->generate('itrblueboost_admin_category_content_generate', [
                    'id_category' => $idCategory,
                ]);
                $jsDef['itrblueboostCategoryContentAcceptUrl'] = $router->generate('itrblueboost_admin_category_content_accept', [
                    'id_category' => $idCategory,
                    'contentId' => 0,
                ]);
                $jsDef['itrblueboostCategoryContentUrl'] = $router->generate('itrblueboost_admin_category_content_index', [
                    'id_category' => $idCategory,
                ]);
            } catch (\Exception $e) {
                // Route not available
            }
        }

        if (!empty($jsDef)) {
            Media::addJsDef($jsDef);
        }

        if ($categoryContentActive && isset($jsDef['itrblueboostCategoryContentPromptsUrl'])) {
            Media::addJsDef([
                'itrblueboostCategoryContentTranslations' => [
                    'modalTitle' => $this->trans('Generate content with AI', [], 'Modules.Itrblueboost.Admin'),
                    'loadingPrompts' => $this->trans('Loading available prompts...', [], 'Modules.Itrblueboost.Admin'),
                    'selectPrompt' => $this->trans('Select a prompt to generate content:', [], 'Modules.Itrblueboost.Admin'),
                    'choosePrompt' => $this->trans('Choose a prompt', [], 'Modules.Itrblueboost.Admin'),
                    'close' => $this->trans('Close', [], 'Admin.Actions'),
                    'generate' => $this->trans('Generate', [], 'Modules.Itrblueboost.Admin'),
                    'generating' => $this->trans('Generating... This may take a few seconds.', [], 'Modules.Itrblueboost.Admin'),
                ],
            ]);
            $this->context->controller->addJS($this->_path . 'views/js/admin-category-content-inline.js?v=' . $this->version);
        }
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
            'bootstrap_version' => Configuration::get(self::CONFIG_BOOTSTRAP_VERSION) ?: 'bootstrap5',
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
            ProductContent::deleteByProduct($idProduct);
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
            'bootstrap_version' => Configuration::get(self::CONFIG_BOOTSTRAP_VERSION) ?: 'bootstrap5',
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
            CategoryContent::deleteByCategory($idCategory);
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
        $hook = new \Itrblueboost\Hooks\DisplayBackOfficeHeader($this);

        return $hook->execute($params);
    }

    /**
     * Hook to provide AI-generated product description to itrmicrodata.
     *
     * @param array<string, mixed> $params Hook parameters containing 'product'
     *
     * @return array<string, string>
     */
    public function hookActionMicrodataProduct(array $params): array
    {
        $hook = new \Itrblueboost\Hooks\ActionMicrodataProduct($this);

        return $hook->execute($params);
    }

    /**
     * Hook to provide AI-generated descriptions for product listings to itrmicrodata.
     *
     * @param array<string, mixed> $params Hook parameters containing 'product'
     *
     * @return array<string, string>
     */
    public function hookActionMicrodataProductList(array $params): array
    {
        $hook = new \Itrblueboost\Hooks\ActionMicrodataProductList($this);

        return $hook->execute($params);
    }

    /**
     * Hook to batch-preload product content before the listing loop.
     *
     * @param array<string, mixed> $params Hook parameters containing 'productIds'
     */
    public function hookActionMicrodataProductListPreload(array $params): void
    {
        $hook = new \Itrblueboost\Hooks\ActionMicrodataProductListPreload($this);
        $hook->execute($params);
    }
}
