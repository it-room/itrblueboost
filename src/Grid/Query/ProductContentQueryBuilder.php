<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Query Builder for Product Content Grid.
 */
class ProductContentQueryBuilder extends AbstractDoctrineQueryBuilder
{
    /** @var DoctrineSearchCriteriaApplicatorInterface */
    private DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator;

    /** @var int */
    private int $contextLangId;

    /** @var int */
    private int $contextShopId;

    /**
     * @param Connection $connection
     * @param string $dbPrefix
     * @param DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator
     * @param int $contextLangId
     * @param int $contextShopId
     */
    public function __construct(
        Connection $connection,
        string $dbPrefix,
        DoctrineSearchCriteriaApplicatorInterface $searchCriteriaApplicator,
        int $contextLangId,
        int $contextShopId
    ) {
        parent::__construct($connection, $dbPrefix);
        $this->searchCriteriaApplicator = $searchCriteriaApplicator;
        $this->contextLangId = $contextLangId;
        $this->contextShopId = $contextShopId;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder($searchCriteria->getFilters());

        $qb->select('c.id_itrblueboost_product_content, c.id_product, c.content_type, c.active, c.status, c.api_content_id, c.prompt_id, cl.generated_content');

        $this->searchCriteriaApplicator->applyPagination($searchCriteria, $qb);
        $this->searchCriteriaApplicator->applySorting($searchCriteria, $qb);

        return $qb;
    }

    /**
     * {@inheritdoc}
     */
    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder($searchCriteria->getFilters());

        $qb->select('COUNT(DISTINCT c.id_itrblueboost_product_content)');

        return $qb;
    }

    /**
     * Build base query.
     *
     * @param array<string, mixed> $filters
     *
     * @return QueryBuilder
     */
    private function getBaseQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'itrblueboost_product_content', 'c')
            ->innerJoin(
                'c',
                $this->dbPrefix . 'itrblueboost_product_content_lang',
                'cl',
                'c.id_itrblueboost_product_content = cl.id_itrblueboost_product_content AND cl.id_lang = :id_lang'
            )
            ->innerJoin(
                'c',
                $this->dbPrefix . 'itrblueboost_product_content_shop',
                'cs',
                'c.id_itrblueboost_product_content = cs.id_itrblueboost_product_content AND cs.id_shop = :id_shop'
            )
            ->setParameter('id_lang', $this->contextLangId)
            ->setParameter('id_shop', $this->contextShopId);

        $this->applyFilters($qb, $filters);

        return $qb;
    }

    /**
     * Apply filters to query.
     *
     * @param QueryBuilder $qb
     * @param array<string, mixed> $filters
     *
     * @return void
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $allowedFilters = [
            'id_itrblueboost_product_content',
            'id_product',
            'content_type',
            'status',
            'active',
        ];

        foreach ($filters as $filterName => $filterValue) {
            if (!in_array($filterName, $allowedFilters, true)) {
                continue;
            }

            if ($filterValue === '' || $filterValue === null) {
                continue;
            }

            switch ($filterName) {
                case 'id_itrblueboost_product_content':
                    $qb->andWhere('c.id_itrblueboost_product_content = :id_content')
                        ->setParameter('id_content', (int) $filterValue);
                    break;

                case 'id_product':
                    $qb->andWhere('c.id_product = :id_product')
                        ->setParameter('id_product', (int) $filterValue);
                    break;

                case 'content_type':
                    $qb->andWhere('c.content_type = :content_type')
                        ->setParameter('content_type', $filterValue);
                    break;

                case 'status':
                    $qb->andWhere('c.status = :status')
                        ->setParameter('status', $filterValue);
                    break;

                case 'active':
                    $qb->andWhere('c.active = :active')
                        ->setParameter('active', (int) $filterValue);
                    break;
            }
        }
    }
}
