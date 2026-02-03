<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Data\Factory;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Factory de donnees pour le Grid FAQ Catégorie.
 */
class CategoryFaqGridDataFactory implements GridDataFactoryInterface
{
    /** @var GridDataFactoryInterface */
    private GridDataFactoryInterface $doctrineDataFactory;

    /**
     * @param GridDataFactoryInterface $doctrineDataFactory
     */
    public function __construct(GridDataFactoryInterface $doctrineDataFactory)
    {
        $this->doctrineDataFactory = $doctrineDataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(SearchCriteriaInterface $searchCriteria): GridData
    {
        $data = $this->doctrineDataFactory->getData($searchCriteria);
        $records = $data->getRecords()->all();

        // Traitement supplementaire des donnees si necessaire
        foreach ($records as $key => $record) {
            // Tronquer la question si trop longue
            if (isset($record['question']) && mb_strlen($record['question']) > 100) {
                $records[$key]['question'] = mb_substr(strip_tags($record['question']), 0, 100) . '...';
            }
        }

        return new GridData(
            new RecordCollection($records),
            $data->getRecordsTotal(),
            $data->getQuery()
        );
    }
}
