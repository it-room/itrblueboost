<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicatorInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Query Builder pour le Grid FAQ Catégorie.
 */
class CategoryFaqQueryBuilder extends AbstractDoctrineQueryBuilder
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

        $qb->select('f.id_itrblueboost_category_faq, f.id_category, f.position, f.active, f.status, f.api_faq_id, fl.question, fl.answer');

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

        $qb->select('COUNT(DISTINCT f.id_itrblueboost_category_faq)');

        return $qb;
    }

    /**
     * Construit la requete de base.
     *
     * @param array<string, mixed> $filters
     *
     * @return QueryBuilder
     */
    private function getBaseQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'itrblueboost_category_faq', 'f')
            ->innerJoin(
                'f',
                $this->dbPrefix . 'itrblueboost_category_faq_lang',
                'fl',
                'f.id_itrblueboost_category_faq = fl.id_itrblueboost_category_faq AND fl.id_lang = :id_lang'
            )
            ->innerJoin(
                'f',
                $this->dbPrefix . 'itrblueboost_category_faq_shop',
                'fs',
                'f.id_itrblueboost_category_faq = fs.id_itrblueboost_category_faq AND fs.id_shop = :id_shop'
            )
            ->setParameter('id_lang', $this->contextLangId)
            ->setParameter('id_shop', $this->contextShopId);

        $this->applyFilters($qb, $filters);

        return $qb;
    }

    /**
     * Applique les filtres a la requete.
     *
     * @param QueryBuilder $qb
     * @param array<string, mixed> $filters
     *
     * @return void
     */
    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        $allowedFilters = [
            'id_itrblueboost_category_faq',
            'id_category',
            'question',
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
                case 'id_itrblueboost_category_faq':
                    $qb->andWhere('f.id_itrblueboost_category_faq = :id_faq')
                        ->setParameter('id_faq', (int) $filterValue);
                    break;

                case 'id_category':
                    $qb->andWhere('f.id_category = :id_category')
                        ->setParameter('id_category', (int) $filterValue);
                    break;

                case 'question':
                    $qb->andWhere('fl.question LIKE :question')
                        ->setParameter('question', '%' . $filterValue . '%');
                    break;

                case 'active':
                    $qb->andWhere('f.active = :active')
                        ->setParameter('active', (int) $filterValue);
                    break;
            }
        }
    }
}
