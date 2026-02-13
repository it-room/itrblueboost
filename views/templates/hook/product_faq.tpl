{if $faqs && count($faqs) > 0}
    <div class="itrblueboost-product-faq">
        {if $bootstrap_version == 'bootstrap5'}
            <div class="accordion" id="faqAccordion" itemscope itemtype="https://schema.org/FAQPage">
                {foreach from=$faqs item=faq key=index}
                    <div class="accordion-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                        <h3 class="accordion-header" id="faqHeading{$faq.id_itrblueboost_product_faq}">
                            <button class="accordion-button{if $index > 0} collapsed{/if}"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#faqCollapse{$faq.id_itrblueboost_product_faq}"
                                    aria-expanded="{if $index == 0}true{else}false{/if}"
                                    aria-controls="faqCollapse{$faq.id_itrblueboost_product_faq}"
                                    itemprop="name">
                                {$faq.question|escape:'html':'UTF-8'}
                            </button>
                        </h3>
                        <div id="faqCollapse{$faq.id_itrblueboost_product_faq}"
                             class="accordion-collapse collapse{if $index == 0} show{/if}"
                             aria-labelledby="faqHeading{$faq.id_itrblueboost_product_faq}"
                             data-bs-parent="#faqAccordion"
                             itemscope
                             itemprop="acceptedAnswer"
                             itemtype="https://schema.org/Answer">
                            <div class="accordion-body" itemprop="text">
                                {$faq.answer nofilter}
                            </div>
                        </div>
                    </div>
                {/foreach}
            </div>
        {else}
            <div id="faqAccordion" role="tablist" itemscope itemtype="https://schema.org/FAQPage">
                {foreach from=$faqs item=faq key=index}
                    <div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                        <div class="card-header" role="tab" id="faqHeading{$faq.id_itrblueboost_product_faq}">
                            <h3 class="mb-0">
                                <a class="itrblueboost-faq-link{if $index > 0} collapsed{/if}"
                                   data-toggle="collapse"
                                   data-target="#faqCollapse{$faq.id_itrblueboost_product_faq}"
                                   href="#faqCollapse{$faq.id_itrblueboost_product_faq}"
                                   aria-expanded="{if $index == 0}true{else}false{/if}"
                                   aria-controls="faqCollapse{$faq.id_itrblueboost_product_faq}"
                                   data-parent="#faqAccordion"
                                   itemprop="name">
                                    {$faq.question|escape:'html':'UTF-8'}
                                </a>
                            </h3>
                        </div>
                        <div id="faqCollapse{$faq.id_itrblueboost_product_faq}"
                             class="collapse{if $index == 0} show{/if}"
                             role="tabpanel"
                             aria-labelledby="faqHeading{$faq.id_itrblueboost_product_faq}"
                             data-parent="#faqAccordion"
                             itemscope
                             itemprop="acceptedAnswer"
                             itemtype="https://schema.org/Answer">
                            <div class="card-body" itemprop="text">
                                {$faq.answer nofilter}
                            </div>
                        </div>
                    </div>
                {/foreach}
            </div>
        {/if}
    </div>

    <style>
        .itrblueboost-product-faq .card {
            border: none;
            margin-bottom: 0.5rem;
            border-radius: 6px;
            overflow: hidden;
        }
        .itrblueboost-product-faq .card-header {
            background: #fff;
            border-bottom: none;
            padding: 0;
        }
        .itrblueboost-product-faq .itrblueboost-faq-link {
            display: block;
            padding: 1rem 1.25rem;
            font-weight: 500;
            color: #232323;
            text-decoration: none;
            position: relative;
            padding-right: 2.5rem;
        }
        .itrblueboost-product-faq .itrblueboost-faq-link:hover {
            text-decoration: none;
            color: #232323;
        }
        .itrblueboost-product-faq .itrblueboost-faq-link::after {
            content: '\f078';
            font-family: 'FontAwesome', 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            transition: transform 0.2s;
            font-size: 0.75rem;
        }
        .itrblueboost-product-faq .itrblueboost-faq-link.collapsed::after {
            transform: translateY(-50%) rotate(-90deg);
        }
        .itrblueboost-product-faq .card-body {
            padding: 0 1.25rem 1rem;
            line-height: 1.7;
        }
    </style>
{/if}
