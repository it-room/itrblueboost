<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;

// PS 8.x uses Common namespace, PS 1.7.x does not
if (!class_exists('Itrblueboost\Grid\Definition\Factory\ContentDataColumn')) {
    if (class_exists('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn')) {
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn', 'Itrblueboost\Grid\Definition\Factory\ContentDataColumn');
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn', 'Itrblueboost\Grid\Definition\Factory\ContentActionColumn');
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn', 'Itrblueboost\Grid\Definition\Factory\ContentBulkActionColumn');
    } else {
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn', 'Itrblueboost\Grid\Definition\Factory\ContentDataColumn');
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn', 'Itrblueboost\Grid\Definition\Factory\ContentActionColumn');
        class_alias('PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn', 'Itrblueboost\Grid\Definition\Factory\ContentBulkActionColumn');
    }
}

class ProductContentGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    public const GRID_ID = 'itrblueboost_product_content';

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->trans('Product Content', [], 'Modules.Itrblueboost.Admin');
    }

    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add(
                (new ContentBulkActionColumn('bulk'))
                    ->setOptions([
                        'bulk_field' => 'id_itrblueboost_product_content',
                    ])
            )
            ->add(
                (new ContentDataColumn('id_itrblueboost_product_content'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_itrblueboost_product_content',
                    ])
            )
            ->add(
                (new ContentDataColumn('content_type'))
                    ->setName($this->trans('Type', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'content_type',
                    ])
            )
            ->add(
                (new ContentDataColumn('generated_content'))
                    ->setName($this->trans('Content', [], 'Modules.Itrblueboost.Admin'))
                    ->setOptions([
                        'field' => 'generated_content',
                    ])
            )
            ->add(
                (new ContentDataColumn('status'))
                    ->setName($this->trans('Status', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'status',
                    ])
            )
            ->add(
                (new ContentActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Global'))
                    ->setOptions([
                        'actions' => (new RowActionCollection())
                            ->add(
                                (new LinkRowAction('edit'))
                                    ->setName($this->trans('Edit', [], 'Admin.Actions'))
                                    ->setIcon('edit')
                                    ->setOptions([
                                        'route' => 'itrblueboost_admin_product_content_edit',
                                        'route_param_name' => 'contentId',
                                        'route_param_field' => 'id_itrblueboost_product_content',
                                        'extra_route_params' => [
                                            'id_product' => 'id_product',
                                        ],
                                    ])
                            )
                            ->add(
                                (new SubmitRowAction('delete'))
                                    ->setName($this->trans('Delete', [], 'Admin.Actions'))
                                    ->setIcon('delete')
                                    ->setOptions([
                                        'method' => 'POST',
                                        'route' => 'itrblueboost_admin_product_content_delete',
                                        'route_param_name' => 'contentId',
                                        'route_param_field' => 'id_itrblueboost_product_content',
                                        'extra_route_params' => [
                                            'id_product' => 'id_product',
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

    protected function getBulkActions(): BulkActionCollection
    {
        return (new BulkActionCollection())
            ->add(
                (new SubmitBulkAction('accept_selection'))
                    ->setName($this->trans('Accept selection', [], 'Modules.Itrblueboost.Admin'))
                    ->setOptions([
                        'submit_route' => 'itrblueboost_admin_product_content_bulk_accept',
                    ])
            )
            ->add(
                (new SubmitBulkAction('reject_selection'))
                    ->setName($this->trans('Reject selection', [], 'Modules.Itrblueboost.Admin'))
                    ->setOptions([
                        'submit_route' => 'itrblueboost_admin_product_content_bulk_reject',
                    ])
            )
            ->add(
                (new SubmitBulkAction('delete_selection'))
                    ->setName($this->trans('Delete selection', [], 'Admin.Actions'))
                    ->setOptions([
                        'submit_route' => 'itrblueboost_admin_product_content_bulk_delete',
                        'confirm_message' => $this->trans('Delete selected contents?', [], 'Modules.Itrblueboost.Admin'),
                    ])
            );
    }
}
