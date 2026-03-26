<?php

declare(strict_types=1);

namespace Itrblueboost\Hooks;

use CmsController;
use Configuration;
use Context;
use Itrblueboost;
use Itrblueboost\Service\FaqApiService;
use Tools;

/**
 * Hook handler for displayCMSDisputeInformation.
 *
 * Fetches CMS FAQs from the external API and renders them
 * below the CMS page content.
 */
class DisplayCMSDisputeInformation
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
        $idCms = $this->extractCmsId($params);

        if ($idCms <= 0) {
            return '';
        }

        $langIso = Context::getContext()->language->iso_code;
        $faqs = $this->faqApiService->getCmsFaqs($idCms, $langIso);

        if (empty($faqs)) {
            return '';
        }

        Context::getContext()->smarty->assign([
            'faqs' => $faqs,
            'bootstrap_version' => Configuration::get(Itrblueboost::CONFIG_BOOTSTRAP_VERSION) ?: 'bootstrap5',
        ]);

        return $this->module->fetch('module:itrblueboost/views/templates/hook/cms_faq.tpl');
    }

    /**
     * @param array<string, mixed> $params
     */
    private function extractCmsId(array $params): int
    {
        $context = Context::getContext();

        if ($context->controller instanceof CmsController && isset($context->controller->cms->id)) {
            return (int) $context->controller->cms->id;
        }

        return (int) Tools::getValue('id_cms');
    }
}
