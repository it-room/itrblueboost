<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Query;

/**
 * Query Builder for Product FAQ Grid.
 */
class ProductFaqQueryBuilder extends AbstractFaqQueryBuilder
{
    /**
     * {@inheritdoc}
     */
    protected function getTableName(): string
    {
        return 'itrblueboost_product_faq';
    }

    /**
     * {@inheritdoc}
     */
    protected function getPrimaryKey(): string
    {
        return 'id_itrblueboost_product_faq';
    }

    /**
     * {@inheritdoc}
     */
    protected function getSelectColumns(): string
    {
        return 'f.id_itrblueboost_product_faq, f.id_product, f.position, f.active, f.status, f.api_faq_id, fl.question, fl.answer';
    }

    /**
     * {@inheritdoc}
     */
    protected function getFilterDefinitions(): array
    {
        return [
            'id_itrblueboost_product_faq' => ['column' => 'f.id_itrblueboost_product_faq', 'type' => 'exact'],
            'id_product' => ['column' => 'f.id_product', 'type' => 'exact'],
            'question' => ['column' => 'fl.question', 'type' => 'like'],
            'active' => ['column' => 'f.active', 'type' => 'exact'],
        ];
    }
}
