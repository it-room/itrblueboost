<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Abstract query builder for FAQ grids (product and category).
 */
abstract class AbstractFaqQueryBuilder extends AbstractDoctrineQueryBuilder
{
    /** @var DoctrineSearchCriteriaApplicatorInterface */
    protected $searchCriteriaApplicator;

    /** @var int */
    protected $contextLangId;

    /** @var int */
    protected $contextShopId;

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
     * @return string Table name without prefix (e.g., 'itrblueboost_product_faq')
     */
    abstract protected function getTableName(): string;

    /**
     * @return string Primary key column (e.g., 'id_itrblueboost_product_faq')
     */
    abstract protected function getPrimaryKey(): string;

    /**
     * @return string SELECT columns for search query
     */
    abstract protected function getSelectColumns(): string;

    /**
     * @return array<string, array{column: string, type: string}> Filter definitions
     */
    abstract protected function getFilterDefinitions(): array;

    /**
     * {@inheritdoc}
     */
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder($searchCriteria->getFilters());

        $qb->select($this->getSelectColumns());

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

        $qb->select('COUNT(DISTINCT f.' . $this->getPrimaryKey() . ')');

        return $qb;
    }

    /**
     * Build the base query with joins and filters.
     *
     * @param array<string, mixed> $filters
     *
     * @return QueryBuilder
     */
    private function getBaseQueryBuilder(array $filters): QueryBuilder
    {
        $table = $this->getTableName();
        $pk = $this->getPrimaryKey();

        $qb = $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . $table, 'f')
            ->innerJoin(
                'f',
                $this->dbPrefix . $table . '_lang',
                'fl',
                'f.' . $pk . ' = fl.' . $pk . ' AND fl.id_lang = :id_lang'
            )
            ->innerJoin(
                'f',
                $this->dbPrefix . $table . '_shop',
                'fs',
                'f.' . $pk . ' = fs.' . $pk . ' AND fs.id_shop = :id_shop'
            )
            ->setParameter('id_lang', $this->contextLangId)
            ->setParameter('id_shop', $this->contextShopId);

        $this->applyFilters($qb, $filters);

        return $qb;
    }

    /**
     * Apply filters to the query.
     *
     * @param QueryBuilder $qb
     * @param array<string, mixed> $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $definitions = $this->getFilterDefinitions();

        foreach ($filters as $filterName => $filterValue) {
            if (!isset($definitions[$filterName])) {
                continue;
            }

            if ($filterValue === '' || $filterValue === null) {
                continue;
            }

            $def = $definitions[$filterName];
            $column = $def['column'];
            $paramName = str_replace('.', '_', $column);

            if ($def['type'] === 'like') {
                $qb->andWhere($column . ' LIKE :' . $paramName)
                    ->setParameter($paramName, '%' . $filterValue . '%');
            } else {
                $qb->andWhere($column . ' = :' . $paramName)
                    ->setParameter($paramName, (int) $filterValue);
            }
        }
    }
}
