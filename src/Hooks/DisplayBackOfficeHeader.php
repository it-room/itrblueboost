<?php

declare(strict_types=1);

namespace Itrblueboost\Hooks;

use Configuration;
use Itrblueboost;

/**
 * Hook handler for displayBackOfficeHeader.
 *
 * Displays the remaining credits badge in the admin header
 * using the value stored in ps_configuration (no API call).
 */
class DisplayBackOfficeHeader
{
    /** @var Itrblueboost */
    private $module;

    public function __construct(Itrblueboost $module)
    {
        $this->module = $module;
    }

    /**
     * Execute the hook logic.
     *
     * @param array<string, mixed> $params Hook parameters
     *
     * @return string
     */
    public function execute(array $params): string
    {
        $apiKey = Configuration::get(Itrblueboost::CONFIG_API_KEY);

        if (empty($apiKey)) {
            return '';
        }

        $credits = Configuration::get(Itrblueboost::CONFIG_CREDITS_REMAINING);

        if ($credits === false || $credits === '') {
            return '';
        }

        $credits = (int) $credits;

        try {
            /** @var \Symfony\Component\Routing\RouterInterface $router */
            $router = $this->module->get('router');
            $configUrl = $router->generate('itrblueboost_configuration');
        } catch (\Exception $e) {
            return '';
        }

        return $this->renderBadge($credits, $configUrl);
    }

    /**
     * Render the credits badge HTML.
     *
     * @param int $credits Remaining credits
     * @param string $configUrl URL to the configuration page
     *
     * @return string
     */
    private function renderBadge(int $credits, string $configUrl): string
    {
        $escapedUrl = htmlspecialchars($configUrl, ENT_QUOTES, 'UTF-8');

        return '
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
                var shopLink = document.querySelector("#header-shop-list-container, .shop-list, a[href*=\'/\'][target=\'_blank\'].header-link, .header_shop_name");

                if (!shopLink) {
                    var allLinks = document.querySelectorAll("#header_infos a, .header-right a, #header_quick a, #header_employee_box a");
                    for (var i = 0; i < allLinks.length; i++) {
                        if (allLinks[i].getAttribute("target") === "_blank" || allLinks[i].textContent.toLowerCase().includes("boutique")) {
                            shopLink = allLinks[i];
                            break;
                        }
                    }
                }

                var badge = document.createElement("a");
                badge.href = "' . $escapedUrl . '";
                badge.className = "itrblueboost-credits-badge";
                badge.innerHTML = \'<i class="material-icons">toll</i><span class="credits-count">' . $credits . '</span> cr\u00e9dits\';
                badge.title = "Cr\u00e9dits ITROOM restants";

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
    }
}
