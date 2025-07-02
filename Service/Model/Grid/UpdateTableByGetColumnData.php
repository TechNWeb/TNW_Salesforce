<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace TNW\Salesforce\Service\Model\Grid;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use TNW\Salesforce\Api\ChunkSizeInterface;
use TNW\Salesforce\Api\Model\Grid\UpdateByDataInterface;

class UpdateTableByGetColumnData implements UpdateByDataInterface
{
    /** @var ResourceConnection */
    private $resourceConnection;

    /** @var GetColumnsDataItems */
    private $getColumnsData;

    /** @var string */
    private $tableName;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param ResourceConnection  $resourceConnection
     * @param GetColumnsDataItems $getColumnsDataItems
     * @param LoggerInterface     $logger
     * @param string              $tableName
     */
    public function __construct(
        ResourceConnection  $resourceConnection,
        GetColumnsDataItems $getColumnsDataItems,
        LoggerInterface     $logger,
        string              $tableName
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->tableName = $tableName;
        $this->logger = $logger;
        $this->getColumnsData = $getColumnsDataItems;
    }

    /**
     * @param array|null $entityIds
     *
     * @return void
     */
    public function execute(?array $entityIds = null): void
    {
        $columnsDataItems = $this->getColumnsData->execute($entityIds);
        if ($columnsDataItems) {
            $adapter = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName($this->tableName);
            foreach (array_chunk($columnsDataItems, ChunkSizeInterface::CHUNK_SIZE_200) as $columnsDataItemsChunk) {
                try {
                    $adapter->beginTransaction();
                    $adapter->insertOnDuplicate($tableName, $columnsDataItemsChunk);
                    $adapter->commit();
                } catch (\Throwable $e) {
                    $adapter->rollBack();
                    $message = implode(PHP_EOL, [$e->getMessage(), $e->getTraceAsString()]);
                    $this->logger->critical($message);
                }
            }
        }
    }
}
