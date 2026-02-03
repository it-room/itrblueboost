<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Definition\Factory;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;

class CategoryFaqGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    public const GRID_ID = 'itrblueboost_category_faq';

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->trans('FAQ Catégorie', [], 'Modules.Itrblueboost.Admin');
    }

    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add(
                (new BulkActionColumn('bulk'))
                    ->setOptions([
                        'bulk_field' => 'id_itrblueboost_category_faq',
                    ])
            )
            ->add(
                (new DataColumn('id_itrblueboost_category_faq'))
                    ->setName($this->trans('ID', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'id_itrblueboost_category_faq',
                    ])
            )
            ->add(
                (new DataColumn('position'))
                    ->setName($this->trans('Position', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'position',
                    ])
            )
            ->add(
                (new DataColumn('question'))
                    ->setName($this->trans('Question', [], 'Modules.Itrblueboost.Admin'))
                    ->setOptions([
                        'field' => 'question',
                    ])
            )
            ->add(
                (new DataColumn('status'))
                    ->setName($this->trans('Statut', [], 'Admin.Global'))
                    ->setOptions([
                        'field' => 'status',
                    ])
            )
            ->add(
                (new ActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Global'))
                    ->setOptions([
                        'actions' => (new RowActionCollection())
                            ->add(
                                (new LinkRowAction('edit'))
                                    ->setName($this->trans('Modifier', [], 'Admin.Actions'))
                                    ->setIcon('edit')
                                    ->setOptions([
                                        'route' => 'itrblueboost_admin_category_faq_edit',
                                        'route_param_name' => 'faqId',
                                        'route_param_field' => 'id_itrblueboost_category_faq',
                                        'extra_route_params' => [
                                            'id_category' => 'id_category',
                                        ],
                                    ])
                            )
                            ->add(
                                (new SubmitRowAction('delete'))
                                    ->setName($this->trans('Supprimer', [], 'Admin.Actions'))
                                    ->setIcon('delete')
                                    ->setOptions([
                                        'method' => 'POST',
                                        'route' => 'itrblueboost_admin_category_faq_delete',
                                        'route_param_name' => 'faqId',
                                        'route_param_field' => 'id_itrblueboost_category_faq',
                                        'extra_route_params' => [
                                            'id_category' => 'id_category',
                                        ],
                                        'confirm_message' => $this->trans(
                                            'Supprimer cette FAQ ?',
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
                    ->setName($this->trans('Accepter la sélection', [], 'Modules.Itrblueboost.Admin'))
                    ->setOptions([
                        'submit_route' => 'itrblueboost_admin_category_faq_bulk_accept',
                    ])
            )
            ->add(
                (new SubmitBulkAction('reject_selection'))
                    ->setName($this->trans('Rejeter la sélection', [], 'Modules.Itrblueboost.Admin'))
                    ->setOptions([
                        'submit_route' => 'itrblueboost_admin_category_faq_bulk_reject',
                    ])
            )
            ->add(
                (new SubmitBulkAction('delete_selection'))
                    ->setName($this->trans('Supprimer la sélection', [], 'Admin.Actions'))
                    ->setOptions([
                        'submit_route' => 'itrblueboost_admin_category_faq_bulk_delete',
                        'confirm_message' => $this->trans('Supprimer les FAQ sélectionnées ?', [], 'Modules.Itrblueboost.Admin'),
                    ])
            );
    }
}
