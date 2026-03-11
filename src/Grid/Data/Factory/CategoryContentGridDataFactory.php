<?php

declare(strict_types=1);

namespace Itrblueboost\Grid\Data\Factory;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Data factory for Category Content Grid.
 */
class CategoryContentGridDataFactory implements GridDataFactoryInterface
{
    /** @var GridDataFactoryInterface */
    private $doctrineDataFactory;

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

        foreach ($records as $key => $record) {
            if (isset($record['generated_content']) && mb_strlen($record['generated_content']) > 150) {
                $records[$key]['generated_content'] = mb_substr(strip_tags($record['generated_content']), 0, 150) . '...';
            }
        }

        return new GridData(
            new RecordCollection($records),
            $data->getRecordsTotal(),
            $data->getQuery()
        );
    }
}
