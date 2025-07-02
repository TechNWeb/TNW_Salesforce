<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace TNW\Salesforce\Model\ResourceModel;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\LocalizedException;
use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Psr\Log\LoggerInterface;
use TNW\Salesforce\Model\Config;

class Objects extends AbstractDb
{
    public const RECORDS_KEY = 'records';
    private const CHUNK_SIZE = 500;

    /**
     * @var \Magento\Framework\DB\Select
     */
    private $selectObjectId;

    /**
     * @var \Magento\Framework\DB\Select
     */
    private $selectObjectIds;

    /**
     * @var \Magento\Framework\DB\Select
     */
    private $selectEntityId;

    /**
     * @var \Magento\Framework\DB\Select
     */
    private $selectEntityIds;

    /**
     * @var \Magento\Framework\DB\Select
     */
    private $selectEntityIdsByType;

    /**
     * @var \Magento\Framework\DB\Select
     */
    private $selectStatus;

    /**
     * @var \Magento\Framework\DB\Select
     */
    private $selectPriceBookId;

    /**
     * @var Manager
     */
    private $eventManager;

    /**
     * @var Config
     */
    private $config;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param Context $context
     * @param Config $config
     * @param LoggerInterface $logger
     * @param null $connectionName
     * @param Manager|null $eventManager
     */
    public function __construct(
        Context           $context,
        Config            $config,
        LoggerInterface   $logger,
                          $connectionName = null,
        ?Manager           $eventManager = null
    ) {
        parent::__construct($context, $connectionName);
        $this->eventManager = $eventManager ?? ObjectManager::getInstance()->get(Manager::class);
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @throws LocalizedException
     */
    protected function _construct()
    {
        $this->_init('tnw_salesforce_objects', 'row_id');

        $this->selectObjectId = $this->getConnection()->select()
            ->from($this->getMainTable(), ['object_id', 'salesforce_type'])
            ->where('magento_type = :magento_type')
            ->where('entity_id = :entity_id')
            ->where('website_id IN (:entity_website_id, :base_website_id)')
            ->order(new \Zend_Db_Expr('FIELD(website_id, :entity_website_id, :base_website_id)'))
            ->limit(1);

        $this->selectObjectIds = $this->getConnection()->select()
            ->from($this->getMainTable(), ['object_id', 'salesforce_type'])
            ->where('magento_type = :magento_type')
            ->where('entity_id = :entity_id')
            ->where('website_id IN (:base_website_id, :entity_website_id)')
            ->order(new \Zend_Db_Expr('FIELD(website_id, :base_website_id, :entity_website_id)'));

        $this->selectEntityId = $this->getConnection()->select()
            ->from($this->getMainTable(), ['entity_id', 'magento_type'])
            ->where('salesforce_type = :salesforce_type')
            ->where('object_id = :object_id')
            ->where('website_id IN (:entity_website_id, :base_website_id)')
            ->order(new \Zend_Db_Expr('FIELD(website_id, :entity_website_id, :base_website_id)'))
            ->limit(1);

        $this->selectEntityIds = $this->getConnection()->select()
            ->from($this->getMainTable(), ['entity_id', 'magento_type'])
            ->where('salesforce_type = :salesforce_type')
            ->where('object_id = :object_id')
            ->where('website_id IN (:entity_website_id, :base_website_id)')
            ->order(new \Zend_Db_Expr('FIELD(website_id, :base_website_id, :entity_website_id)'));

        $this->selectEntityIdsByType = $this->getConnection()->select()
            ->from($this->getMainTable(), ['entity_id'])
            ->where('magento_type = :magento_type')
            ->where('salesforce_type = :salesforce_type')
            ->where('object_id = :object_id')
            ->where('website_id IN (:entity_website_id, :base_website_id)')
            ->order('website_id');

        $this->selectStatus = $this->getConnection()->select()
            ->from($this->getMainTable(), ['status' => new \Zend_Db_Expr('MAX(status)')])
            ->where('magento_type = :magento_type')
            ->where('entity_id = :entity_id')
            ->where('website_id IN(:entity_website_id, :base_website_id)');

        $this->selectPriceBookId = $this->getConnection()->select()
            ->from($this->getMainTable(), ['GROUP_CONCAT(DISTINCT(object_id) SEPARATOR "\n")'])
            ->where('magento_type = "Product"')
            ->where('salesforce_type = "PricebookEntry"')
            ->where('entity_id = :entity_id')
            ->where('website_id IN(:entity_website_id, :base_website_id)')
            ->order('website_id DESC')
            ->group('website_id')
            ->limit(1);
    }

    /**
     * @param $websiteId
     *
     * @return int
     */
    public function baseWebsiteId($websiteId)
    {
        return $this->config->baseWebsiteIdLogin($websiteId);
    }

    /**
     * @param int         $entityId
     * @param string      $magentoType
     * @param int         $websiteId
     * @param string|null $salesforceType
     *
     * @return string
     */
    public function loadObjectId($entityId, $magentoType, $websiteId, ?string $salesforceType = null)
    {
        $loadObjectIdSelect = clone $this->selectObjectId;
        if ($salesforceType !== null) {
            $loadObjectIdSelect->where('salesforce_type = ?', $salesforceType);
        }

        return $this->getConnection()->fetchOne($loadObjectIdSelect, [
            'magento_type' => $magentoType,
            'entity_id' => (int)$entityId,
            'entity_website_id' => (int)$websiteId,
            'base_website_id' => (int)$this->baseWebsiteId($websiteId),
        ]);
    }

    /**
     * @param int      $productId
     * @param int      $websiteId
     *
     * @param int|null $storeId
     *
     * @return string
     */
    public function loadPriceBookId(int $productId, int $websiteId, ?int $storeId = null): string
    {
        $loadPricebookIdSelect = clone $this->selectPriceBookId;
        if ($storeId) {
            $loadPricebookIdSelect->where('store_id = ?', $storeId);
        }

        return (string)$this->getConnection()->fetchOne($loadPricebookIdSelect, [
            'entity_id' => $productId,
            'entity_website_id' => $websiteId,
            'base_website_id' => $this->baseWebsiteId($websiteId),
        ]);
    }

    /**
     * @param int         $entityId
     * @param string      $magentoType
     * @param int         $websiteId
     * @param string|null $salesforceType
     *
     * @return int
     */
    public function loadStatus($entityId, $magentoType, $websiteId, ?string $salesforceType = null)
    {
        $loadObjectStatusSelect = clone $this->selectStatus;
        if ($salesforceType !== null) {
            $loadObjectStatusSelect->where('salesforce_type = ?', $salesforceType);
        }

        $result = $this->getConnection()->fetchOne($loadObjectStatusSelect, [
            'magento_type' => $magentoType,
            'entity_id' => $entityId,
            'entity_website_id' => $websiteId,
            'base_website_id' => $this->baseWebsiteId($websiteId),
        ]);

        return (int)$result;
    }

    /**
     * @param string $objectId
     * @param string $salesforceType
     * @param int    $websiteId
     *
     * @return int
     */
    public function loadEntityId($objectId, $salesforceType, $websiteId)
    {
        return $this->getConnection()->fetchOne($this->selectEntityId, [
            'salesforce_type' => $salesforceType,
            'object_id' => $objectId,
            'entity_website_id' => $websiteId,
            'base_website_id' => $this->baseWebsiteId($websiteId),
        ]);
    }

    /**
     * @param string $objectId
     * @param string $salesforceType
     * @param int    $websiteId
     *
     * @return array
     */
    public function loadEntityIds($objectId, $salesforceType, $websiteId)
    {
        return array_flip($this->getConnection()->fetchPairs($this->selectEntityId, [
            'salesforce_type' => $salesforceType,
            'object_id' => $objectId,
            'entity_website_id' => $websiteId,
            'base_website_id' => $this->baseWebsiteId($websiteId),
        ]));
    }

    /**
     * @param array $records
     *
     * @throws LocalizedException
     * @throws \Throwable
     */
    public function saveRecords(array $records)
    {
        if (empty($records)) {
            return;
        }

        $records = array_map(function (array $record) {
            return array_intersect_key($record, array_flip([
                'magento_type',
                'entity_id',
                'object_id',
                'salesforce_type',
                'status',
                'website_id',
                'store_id',
                'additional'
            ]));
        }, $records);
        $this->eventManager->dispatch('tnw_salesforce_objects_save_before', [self::RECORDS_KEY => $records]);
        $connection = $this->getConnection();
        try {
            $connection->beginTransaction();
            foreach (array_chunk($records, self::CHUNK_SIZE) as $recordsChunk) {
                $connection
                    ->insertOnDuplicate($this->getMainTable(), $recordsChunk);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $this->eventManager->dispatch('tnw_salesforce_objects_save_after', [self::RECORDS_KEY => $records]);
    }

    /**
     * @param int    $entityId
     * @param string $magentoType
     * @param int    $status
     * @param int    $websiteId
     *
     * @throws LocalizedException
     */
    public function saveStatus($entityId, $magentoType, $status, $websiteId)
    {
        $this->getConnection()
            ->update(
                $this->getMainTable(),
                ['status' => (int)$status],
                "entity_id = $entityId AND magento_type = '$magentoType' AND website_id = {$websiteId}"
            );
    }

    /**
     * @param array $records
     *
     * @throws LocalizedException
     * @throws \Throwable
     */
    public function saveStatusMass(array $records): void
    {
        $parts = [];
        foreach ($records as $websiteId => $groupedByStatus) {
            foreach ($groupedByStatus as $status => $groupedByMagentoType) {
                foreach ($groupedByMagentoType as $magentoType => $entityIds) {
                    if ($entityIds) {
                        $entityIds = array_unique($entityIds);
                        $parts[] = [
                            'website_id' => $websiteId,
                            'status' => $status,
                            'magento_type' => $magentoType,
                            'entity_id' => $entityIds
                        ];
                    }
                }
            }
        }
        $connection = $this->getConnection();
        foreach ($parts as $part) {
            $websiteId = $part['website_id'];
            $status = $part['status'];
            $magentoType = $part['magento_type'];
            $entityIds = $part['entity_id'] ?? [];
            foreach (array_chunk($entityIds, self::CHUNK_SIZE) as $entityIdsChunk) {
                $entityIdsChunk = implode(',', $entityIdsChunk);
                try {
                    $connection->beginTransaction();
                    $connection->update(
                        $this->getMainTable(),
                        ['status' => (int)$status],
                        sprintf(
                            "entity_id IN (%s) AND magento_type = '%s' AND website_id = %d",
                            $entityIdsChunk,
                            $magentoType,
                            (int)$websiteId
                        )
                    );
                    $connection->commit();
                } catch (\Throwable $e) {
                    $connection->rollBack();
                    throw $e;
                }
            }
        }
    }

    /**
     * @param int|array $entityId
     * @param string    $magentoType
     * @param int       $websiteId
     *
     * @throws LocalizedException
     */
    public function setPendingStatus($entityId, $magentoType, $websiteId)
    {
        if (!$entityId) {
            return;
        }

        $entityIds = !is_array($entityId) ? [$entityId] : $entityId;
        $entityIds = array_map('intval', $entityIds);

        $connection = $this->getConnection();
        foreach (array_chunk($entityIds, self::CHUNK_SIZE) as $entityIdsChunk) {
            try {
                $connection->beginTransaction();
                $connection->update(
                    $this->getMainTable(),
                    ['status' => new \Zend_Db_Expr('(status + 10)')],
                    new \Zend_Db_Expr(
                        sprintf(
                            'entity_id IN (%s) AND magento_type = \'%s\' AND website_id = %d AND status < 10',
                            implode(',', $entityIdsChunk),
                            $magentoType,
                            $websiteId
                        )
                    )
                );
                $connection->commit();
            } catch (\Throwable $e) {
                $this->logger->critical($e->getMessage());
                $connection->rollBack();
            }
        }
    }

    /**
     * @param int|array   $entityId
     * @param string      $magentoType
     * @param int         $websiteId
     * @param string|null $salesforceType
     */
    public function unsetPendingStatus($entityId, $magentoType, $websiteId, $salesforceType = null)
    {
        if (!$entityId) {
            return;
        }

        $entityIds = !is_array($entityId) ? [$entityId] : $entityId;
        $entityIds = array_map('intval', $entityIds);

        $connection = $this->getConnection();
        foreach (array_chunk($entityIds, self::CHUNK_SIZE) as $entityIdsChunk) {
            $whereCondition = 'entity_id IN (%s) AND magento_type = \'%s\' AND website_id = %d AND status > 9';
            if ($salesforceType) {
                $whereCondition .= ' AND salesforce_type = \'%s\'';
                $where = new \Zend_Db_Expr(sprintf($whereCondition, implode(',', $entityIdsChunk), $magentoType, $websiteId, $salesforceType));
            } else {
                $where = new \Zend_Db_Expr(sprintf($whereCondition, implode(',', $entityIdsChunk), $magentoType, $websiteId));
            }
            try {
                $connection->beginTransaction();
                $connection->update(
                    $this->getMainTable(),
                    ['status' => new \Zend_Db_Expr('status - 10')],
                    $where
                );
                $connection->commit();
            } catch (\Throwable $e) {
                $this->logger->critical($e->getMessage());
                $connection->rollBack();
            }
        }
    }

    /**
     * @param string $objectId
     * @param string $magentoType
     * @param string $salesforceType
     * @param int    $websiteId
     *
     * @return array
     */
    public function loadEntityIdsByType($objectId, $magentoType, $salesforceType, $websiteId)
    {
        return $this->getConnection()->fetchCol($this->selectEntityIdsByType, [
            'magento_type' => $magentoType,
            'salesforce_type' => $salesforceType,
            'object_id' => $objectId,
            'entity_website_id' => $websiteId,
            'base_website_id' => $this->baseWebsiteId($websiteId),
        ]);
    }
}
