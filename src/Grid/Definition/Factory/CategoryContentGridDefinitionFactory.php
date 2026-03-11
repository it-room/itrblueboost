<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;

// PS 8.x uses Common namespace, PS 1.7.x does not
if (!class_exists('Itrblueboost\Grid\Definition\Factory\CatContentDataColumn')) {
    if (class_exists('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn')) {
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn', 'Itrblueboost\Grid\Definition\Factory\CatContentDataColumn');
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn', 'Itrblueboost\Grid\Definition\Factory\CatContentActionColumn');
    } else {
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn', 'Itrblueboost\Grid\Definition\Factory\CatContentDataColumn');
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn', 'Itrblueboost\Grid\Definition\Factory\CatContentActionColumn');
    }
}

class CategoryContentGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    public const GRID_ID = 'itrblueboost_category_content';

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->trans('Category Content', [], 'Modules.Itrblueboost.Admin');
    }

    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add(
                (new CatContentDataColumn('id_itrblueboost_category_content'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_itrblueboost_category_content',
                    ])
            )
            ->add(
                (new CatContentDataColumn('generated_content'))
                    ->setName($this->trans('Content', [], 'Modules.Itrblueboost.Admin'))
                    ->setOptions([
                        'field' => 'generated_content',
                    ])
            )
            ->add(
                (new CatContentDataColumn('status'))
                    ->setName($this->trans('Status', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'status',
                    ])
            )
            ->add(
                (new CatContentActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Global'))
                    ->setOptions([
                        'actions' => (new RowActionCollection())
                            ->add(
                                (new LinkRowAction('edit'))
                                    ->setName($this->trans('Edit', [], 'Admin.Actions'))
                                    ->setIcon('edit')
                                    ->setOptions([
                                        'route' => 'itrblueboost_admin_category_content_edit',
                                        'route_param_name' => 'contentId',
                                        'route_param_field' => 'id_itrblueboost_category_content',
                                        'extra_route_params' => [
                                            'id_category' => 'id_category',
                                        ],
                                    ])
                            )
                            ->add(
                                (new SubmitRowAction('delete'))
                                    ->setName($this->trans('Delete', [], 'Admin.Actions'))
                                    ->setIcon('delete')
                                    ->setOptions([
                                        'method' => 'POST',
                                        'route' => 'itrblueboost_admin_category_content_delete',
                                        'route_param_name' => 'contentId',
                                        'route_param_field' => 'id_itrblueboost_category_content',
                                        'extra_route_params' => [
                                            'id_category' => 'id_category',
                                        ],
                                        'confirm_message' => $this->trans(
                                            'Delete this content?',
                                            [],
                                            'Modules.Itrblueboost.Admin'
                                        ),
                                    ])
                            ),
                    ])
            );
    }

    protected function getFilters(): FilterCollection
    {
        return new FilterCollection();
    }
}
