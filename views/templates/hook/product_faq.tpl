{if $faqs && count($faqs) > 0}
    <div class="itrblueboost-product-faq">
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
    </div>
{/if}
