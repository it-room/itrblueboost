{if $faqs && count($faqs) > 0}
    <section class="itrblueboost-category-faq">
        <h2 class="itrblueboost-faq-title">{l s='Questions fréquentes' d='Modules.Itrblueboost.Shop'}</h2>
        <div class="accordion" id="categoryFaqAccordion" itemscope itemtype="https://schema.org/FAQPage">
            {foreach from=$faqs item=faq key=index}
                <div class="accordion-item" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
                    <h3 class="accordion-header" id="categoryFaqHeading{$faq.id_itrblueboost_category_faq}">
                        <button class="accordion-button{if $index > 0} collapsed{/if}"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#categoryFaqCollapse{$faq.id_itrblueboost_category_faq}"
                                aria-expanded="{if $index == 0}true{else}false{/if}"
                                aria-controls="categoryFaqCollapse{$faq.id_itrblueboost_category_faq}"
                                itemprop="name">
                            {$faq.question|escape:'html':'UTF-8'}
                        </button>
                    </h3>
                    <div id="categoryFaqCollapse{$faq.id_itrblueboost_category_faq}"
                         class="accordion-collapse collapse{if $index == 0} show{/if}"
                         aria-labelledby="categoryFaqHeading{$faq.id_itrblueboost_category_faq}"
                         data-bs-parent="#categoryFaqAccordion"
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
    </section>

    <style>
        .itrblueboost-category-faq {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .itrblueboost-faq-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            color: #232323;
        }
        .itrblueboost-category-faq .accordion-item {
            border: none;
            margin-bottom: 0.5rem;
            border-radius: 6px;
            overflow: hidden;
        }
        .itrblueboost-category-faq .accordion-button {
            font-weight: 500;
            background: #fff;
            box-shadow: none;
            padding: 1rem 1.25rem;
        }
        .itrblueboost-category-faq .accordion-button:not(.collapsed) {
            background: #fff;
            color: #232323;
        }
        .itrblueboost-category-faq .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(0,0,0,.125);
        }
        .itrblueboost-category-faq .accordion-body {
            padding: 0 1.25rem 1rem;
            background: #fff;
            line-height: 1.7;
        }
    </style>
{/if}
