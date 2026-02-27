<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Query;

/**
 * Query Builder for Category FAQ Grid.
 */
class CategoryFaqQueryBuilder extends AbstractFaqQueryBuilder
{
    /**
     * {@inheritdoc}
     */
    protected function getTableName(): string
    {
        return 'itrblueboost_category_faq';
    }

    /**
     * {@inheritdoc}
     */
    protected function getPrimaryKey(): string
    {
        return 'id_itrblueboost_category_faq';
    }

    /**
     * {@inheritdoc}
     */
    protected function getSelectColumns(): string
    {
        return 'f.id_itrblueboost_category_faq, f.id_category, f.position, f.active, f.status, f.api_faq_id, fl.question, fl.answer';
    }

    /**
     * {@inheritdoc}
     */
    protected function getFilterDefinitions(): array
    {
        return [
            'id_itrblueboost_category_faq' => ['column' => 'f.id_itrblueboost_category_faq', 'type' => 'exact'],
            'id_category' => ['column' => 'f.id_category', 'type' => 'exact'],
            'question' => ['column' => 'fl.question', 'type' => 'like'],
            'active' => ['column' => 'f.active', 'type' => 'exact'],
        ];
    }
}
