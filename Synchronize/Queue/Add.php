<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Queue;

use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use TNW\Salesforce\Api\ChunkSizeInterface;
use TNW\Salesforce\Api\MessageQueue\PublisherAdapter;
use TNW\Salesforce\Api\Model\Synchronization\ConfigInterface;
use TNW\Salesforce\Model\CleanLocalCache\CleanableObjectsList;
use TNW\Salesforce\Model\Config;
use TNW\Salesforce\Model\Config\WebsiteEmulator;
use TNW\Salesforce\Model\Prequeue\Process;
use TNW\Salesforce\Model\Queue;
use TNW\Salesforce\Model\ResourceModel\PreQueue;
use TNW\Salesforce\Service\Model\Grid\UpdateGridsByQueues;
use TNW\Salesforce\Service\Synchronize\Queue\Add\AddDependenciesForProcessingRows;
use TNW\Salesforce\Service\Synchronize\Queue\Add\UnsetPendingStatusFromPool;
use TNW\Salesforce\Synchronize\Entity\DivideEntityByWebsiteOrg\Pool;
use Zend_Db_Expr;

/**
 * Class Entity
 */
class Add
{
    const DIRECT_ADD_TO_QUEUE_COUNT_LIMIT = 200;

    const TOPIC_NAME = 'tnw_salesforce.sync.realtime';
    /**
     * @var string
     */
    protected $entityType;

    /**
     * @var Unit[]
     */
    public $resolves;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Pool
     */
    protected $dividerPool;

    /**
     * @var WebsiteEmulator
     */
    protected $websiteEmulator;

    /**
     * @var Synchronize
     */
    protected $synchronizeEntity;

    /**
     * @var \TNW\Salesforce\Model\ResourceModel\Queue
     */
    protected $resourceQueue;

    /**
     * @var PreQueue
     */
    protected $resourcePreQueue;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /** @var State */
    protected $state;

    /** @var Config  */
    protected $salesforceConfig;

    /**
     * @var PublisherAdapter
     */
    protected $publisher;

    /** @var AddDependenciesForProcessingRows */
    private $addDependenciesForProcessingRows;

    /** @var UnsetPendingStatusFromPool */
    private $unsetPendingStatusFromPool;

    /** @var CleanableObjectsList */
    private $cleanableObjectsList;

    /** @var UpdateGridsByQueues */
    private $updateGridsByQueues;

    /**
     * Add constructor.
     *
     * @param                                           $entityType
     * @param array                                     $resolves
     * @param StoreManagerInterface                     $storeManager
     * @param Pool                                      $dividerPool
     * @param WebsiteEmulator                           $websiteEmulator
     * @param Synchronize                               $synchronizeEntity
     * @param \TNW\Salesforce\Model\ResourceModel\Queue $resourceQueue
     * @param PreQueue                                  $resourcePreQueue
     * @param ManagerInterface                          $messageManager
     * @param State                                     $state
     * @param Config                                    $salesforceConfig
     * @param PublisherAdapter                          $publisher
     * @param AddDependenciesForProcessingRows          $addDependenciesForProcessingRows
     * @param UnsetPendingStatusFromPool                $unsetPendingStatusFromPool
     * @param CleanableObjectsList                      $cleanableObjectsList
     * @param UpdateGridsByQueues                       $updateGridsByQueues
     */
    public function __construct(
        $entityType,
        array $resolves,
        StoreManagerInterface $storeManager,
        Pool $dividerPool,
        WebsiteEmulator $websiteEmulator,
        Synchronize $synchronizeEntity,
        \TNW\Salesforce\Model\ResourceModel\Queue $resourceQueue,
        PreQueue $resourcePreQueue,
        ManagerInterface $messageManager,
        State $state,
        Config $salesforceConfig,
        PublisherAdapter $publisher,
        AddDependenciesForProcessingRows $addDependenciesForProcessingRows,
        UnsetPendingStatusFromPool $unsetPendingStatusFromPool,
        CleanableObjectsList $cleanableObjectsList,
        UpdateGridsByQueues $updateGridsByQueues
    ) {
        $this->resolves = $resolves;
        $this->entityType = $entityType;
        $this->storeManager = $storeManager;
        $this->dividerPool = $dividerPool;
        $this->websiteEmulator = $websiteEmulator;
        $this->synchronizeEntity = $synchronizeEntity;
        $this->resourceQueue = $resourceQueue;
        $this->resourcePreQueue = $resourcePreQueue;
        $this->messageManager = $messageManager;
        $this->state = $state;
        $this->salesforceConfig = $salesforceConfig;
        $this->publisher = $publisher;
        $this->addDependenciesForProcessingRows = $addDependenciesForProcessingRows;
        $this->unsetPendingStatusFromPool = $unsetPendingStatusFromPool;
        $this->cleanableObjectsList = $cleanableObjectsList;
        $this->updateGridsByQueues = $updateGridsByQueues;
    }

    /**
     * Add To Queue
     *
     * @param int[] $entityIds
     * @throws LocalizedException
     */
    public function addToQueue(array $entityIds)
    {
        if (!$this->salesforceConfig->getSalesforceStatus()) {
            return;
        }

        if (empty($entityIds)) {
            return;
        }

        $this->addToPreQueue($entityIds);
    }

    /**
     * @param array $entityIds
     */
    public function addToPreQueue(array $entityIds)
    {
        foreach (array_chunk($entityIds, self::DIRECT_ADD_TO_QUEUE_COUNT_LIMIT) as $ids) {
            $syncType = $this->syncType(count($entityIds), 0);
            $this->resourcePreQueue->saveEntityIds($ids, $this->entityType, $syncType);
        }

        if (!empty($entityIds)) {
            $this->publisher->publish(Process::MQ_TOPIC_NAME, false);
        }

        if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {

            /** @var MessageInterface $message */
            $message = $this->messageManager
                ->createMessage(MessageInterface::TYPE_SUCCESS)
                ->setText('Records are being added to the synchronization queue');

            $this->messageManager->addUniqueMessages([$message]);
        }
    }

    /**
     * @param array $entityIds
     * @throws LocalizedException
     */
    public function addToQueueDirectly(array $entityIds, $syncType = null)
    {
        $entitiesByWebsite = $this->dividerPool
            ->getDividerByGroupCode($this->entityType)
            ->process($entityIds);

        array_walk($entitiesByWebsite, [$this, 'addToQueueByWebsite'], $syncType);
    }

    /**
     * Add To Queue By Website
     *
     * @param int[] $entityIds
     * @param null|bool|int|string|WebsiteInterface $website
     * @throws LocalizedException
     * @throws Exception
     */
    public function addToQueueByWebsite(array $entityIds, $website = null, $syncType = null)
    {
        $websiteId = $this->storeManager->getWebsite($website)->getId();

        if ($syncType === null) {
            $syncType = $this->syncType(count($entityIds), $websiteId);
        }

        $this->create($this->resolves, $this->entityType, $entityIds, [], $websiteId, $syncType);

        if ($syncType === ConfigInterface::DIRECT_SYNC_TYPE_REALTIME) {
            // Sync realtime type
            $this->publisher->publish(self::TOPIC_NAME, (string)$websiteId);
            return;
        }

        if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
            $this->messageManager->addSuccessMessage('All records were added to the synchronization queue');
        }
    }

    /**
     * @param Queue[] $current
     * @param array $parentQueuesByBaseEntityId
     *
     * @return array
     */
    public function buildDependency($current, $parentQueuesByBaseEntityId)
    {
        if (!$parentQueuesByBaseEntityId) {
            return [];
        }

        $dependency = [];
        foreach ($current as $queue) {
            $entityId = $queue->getEntityId();
            $parents = $parentQueuesByBaseEntityId[$entityId] ?? [];
            foreach ($parents as $identify => $parent) {
                if ($identify !== $queue->getIdentify() && $this->checkAdditional($queue, $parent)) {
                    $dependency[] = [
                        'parent_id' => $parent->getId(),
                        'queue_id' => $queue->getId(),
                        'parent_status' => Queue::STATUS_NEW
                    ];
                }
            }
        }

        return $dependency;
    }

    /**
     * @param Queue $queue
     * @param Queue $parentQueue
     *
     * @return bool
     */
    public function checkAdditional(Queue $queue, Queue $parentQueue): bool
    {
        $result = true;
        if ($queue->getCode() === Unit::PRODUCT_PRICE_BOOK_ENTRY &&
            $parentQueue->getCode() === Unit::PRODUCT_PRICE_BOOK_ENTRY
        ) {
            $entityLoadAdditional = $queue->getEntityLoadAdditional() ?? [];
            $parentEntityLoadAdditional = $parentQueue->getEntityLoadAdditional() ?? [];
            if (is_array($entityLoadAdditional) && is_array($parentEntityLoadAdditional)) {
                $currency = (string)($entityLoadAdditional['currency_code'] ?? '');
                $parentCurrency = (string)($parentEntityLoadAdditional['currency_code'] ?? '');

                $result = $currency === $parentCurrency;
            }
        }

        return $result;
    }

    /**
     * @param $unitsList Unit[]
     * @param $loadBy
     * @param $entityIds
     * @param $loadAdditional
     * @param $websiteId
     * @param $dependencies
     * @return Queue[]
     * @throws LocalizedException
     */
    public function generateQueueObjects(
        $unitsList,
        $loadBy,
        $entityIds,
        $loadAdditional,
        $websiteId,
        &$dependencies,
        &$queuesUnique = [],
        $relatedUnitCode = null
    ) {
        $queues = [];
        $parents = $children = [];

        /**
         * save related entities to the Queue
         */
        foreach ($unitsList as $key => $unit) {
            $key = (sprintf(
                '%s',
                $unit->code()
            ));

            $current = $this->getCurrent($unit, $loadBy, $entityIds, $loadAdditional, $websiteId, $relatedUnitCode);

            if (empty($current)) {
                continue;
            }

            // merge already created records
            if (isset($queuesUnique[$key])) {
                $current = $unit->baseByUnique($queuesUnique[$key], $current);
            }

            $currentEntityIds = [];
            $currentByEntityLoad = [];
            foreach ([$current] as $relation) {
                foreach ($relation as $relationItem) {
                    if (empty($queuesUnique[$key][$relationItem->getId()])) {
                        $currentEntityIds[$relationItem->getEntityLoad()][$relationItem->getEntityId()] = $relationItem->getEntityId();
                        $currentByEntityLoad[$relationItem->getEntityLoad()][$relationItem->getEntityId()] = $relationItem;
                        $queuesUnique[$key][$relationItem->getId()] = $relationItem;
                    }
                }
            }

            foreach ($currentByEntityLoad as $baseEntityLoad => $itemByEntityLoad) {
                /** @var Queue */
                $currentItem = reset($itemByEntityLoad);
                $baseEntityLoadAdditional = $currentItem->getEntityLoadAdditional();

                $closure = function ($unitsList, &$result) use (
                    $unit,
                    $baseEntityLoad,
                    $currentEntityIds,
                    $baseEntityLoadAdditional,
                    $websiteId,
                    &$dependencies,
                    &$queuesUnique
                ) {
                    $tmp = $this->generateQueueObjects(
                        $unitsList,
                        $baseEntityLoad,
                        $currentEntityIds[$baseEntityLoad],
                        $baseEntityLoadAdditional,
                        $websiteId,
                        $dependencies,
                        $queuesUnique,
                        $unit->code()
                    );

                    if (!empty($tmp)) {
                        $tmp = array_values($tmp);
                        array_push($result, ...$tmp);
                    }
                };

                $parentUnits = $unit->parents();
                $parentUnits && $closure($parentUnits, $parents);
                $childrenUnits = $unit->children();
                $childrenUnits && $closure($childrenUnits, $children);

            }

            $dependencies = $this->addDependencies($unit, $current, $dependencies);

            $unit->addQueues($current);

            foreach ([$current, $parents, $children] as $relation) {
                foreach ($relation as $relationItem) {
                    $queues[$relationItem->getId()] = $relationItem;
                }
            }
        }

        return $queues;
    }

    /**
     * @param $unit
     * @param $loadBy
     * @param $entityIds
     * @param $loadAdditional
     * @param $websiteId
     * @param $relatedUnitCode
     * @return mixed
     */
    public function getCurrent($unit, $loadBy, $entityIds, $loadAdditional, $websiteId, $relatedUnitCode)
    {
        $this->cleanableObjectsList->add($unit);

        $relatedUnitCode = $relatedUnitCode ?? $unit->code();
        return $unit->generateQueues($loadBy, $entityIds, $loadAdditional, $websiteId, $relatedUnitCode);
    }

    /**
     * @param Unit $unit
     * @param $current
     * @param $dependencies
     * @return mixed
     */
    public function addDependencies($unit, $current, $dependencies)
    {
        $newDependenciesApplied = false;
        /**
         * add parent dependency only, child has own relations
         * and will be created as parent dependency deeper in recursion generateQueueObjects
         */
        if (!empty($unit->parents())) {

            foreach ($unit->parents() as $parent) {
                $unitCode = $unit->code();
                $newDependencies = $this->buildDependency($current, $parent->getQueuesGroupedByBaseEntityIds($unitCode));
                if (!empty($newDependencies)) {
                    $newDependenciesApplied = true;
                    array_push($dependencies, ...$newDependencies);
                }
            }
        }

        if (empty($unit->parents()) || !$newDependenciesApplied) {
            foreach ($current as $queue) {
                $dependencies[] = [
                    'parent_id' => null,
                    'queue_id' => $queue->getId(),
                    'parent_status' => Queue::STATUS_COMPLETE
                ];
            }
        }

        return $dependencies;
    }

    /**
     * @param $queues
     * @return array
     * @throws LocalizedException
     */
    public function getInsertArray($queues, $syncType, $websiteId)
    {
        $queueDataToSave = [];

        foreach ($queues as $queue) {
            $queue->setData('website_id', $websiteId);
            $queue->setData('sync_type', $syncType);
            $queueDataToSave[] = $this->resourceQueue->convertToArray($queue);
        }

        return $queueDataToSave;
    }

    /**
     * Create
     *
     * @param $unitsList Unit[]
     * @param $loadBy
     * @param $entityIds
     * @param array $loadAdditional
     * @param $websiteId
     * @param $syncType
     * @return array
     * @throws LocalizedException
     */
    public function create(
        $unitsList,
        $loadBy,
        $entityIds,
        array $loadAdditional,
        $websiteId,
        $syncType
    ) {
        $dependencies = [];

        /**
         * collect all queue objects and build dependencies
         */
        $queues = $this->generateQueueObjects(
            $unitsList,
            $loadBy,
            $entityIds,
            $loadAdditional,
            $websiteId,
            $dependencies
        );

        $this->unsetPendingStatusFromPool->execute();

        $queueDataToSave = $this->getInsertArray($queues, $syncType, $websiteId);

        $dependencies = $this->addDependenciesForProcessingRows->execute($queueDataToSave, $dependencies);

        $this->saveData($queueDataToSave, $dependencies);

        $this->updateGridsByQueues->execute($queues);

    }

    /**
     * @throws Exception
     */
    public function saveData($queueDataToSave, $dependencies)
    {
        $connection = $this->resourceQueue->getConnection();

        $connection->beginTransaction();
        try {
            $this->saveQueue($queueDataToSave);
            $this->saveDependency($dependencies);
            $this->updateRelationsWithErrors($queueDataToSave);

            $connection->commit();
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * @param $queueDataToSave
     * @throws LocalizedException
     */
    public function saveQueue($queueDataToSave)
    {
        if (!empty($queueDataToSave)) {
            $this
                ->resourceQueue
                ->getConnection()
                ->insertOnDuplicate(
                    $this->resourceQueue->getMainTable(),
                    $queueDataToSave,
                    array_keys(reset($queueDataToSave))
                );
        }
    }

    /**
     * @param $dependencies
     * @throws LocalizedException
     */
    public function saveDependency($dependencies)
    {
        if (!empty($dependencies)) {
            $this
                ->resourceQueue
                ->getConnection()
                ->insertArray(
                    $this->resourceQueue->getTable('tnw_salesforce_entity_queue_relation'),
                    array_keys(reset($dependencies)),
                    $dependencies,
                    AdapterInterface::INSERT_IGNORE
                );
        }
    }

    /**
     * Sync Type
     *
     * @param int $count
     * @param int $websiteId
     * @return int
     */
    public function syncType($count, $websiteId)
    {
        return ConfigInterface::DIRECT_SYNC_TYPE_REALTIME;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * If any of saved queues have some parent queue with 'error' status,
     * make such parent queue 'new' to unblock newly added queues.
     *
     * @param array $queueDataToSave
     * @return void
     */
    private function updateRelationsWithErrors(array $queueDataToSave): void
    {
        if (!empty($queueDataToSave)) {

            foreach (array_chunk($queueDataToSave, ChunkSizeInterface::CHUNK_SIZE_200) as $queueDataToSaveBatch) {
                $orWhere = array_map(function (array $queue) {
                    return sprintf(
                        '(old_queue.identify = \'%s\' AND old_queue.website_id= \'%s\')',
                        $queue['identify'],
                        $queue['website_id']
                    );
                },
                    $queueDataToSaveBatch
                );
                $connection = $this->resourceQueue->getConnection();
                $queueTable = $connection->getTableName('tnw_salesforce_entity_queue');
                $relationTable = $connection->getTableName('tnw_salesforce_entity_queue_relation');
                $select = $connection->select()
                    ->distinct()
                    ->join(
                        ['relation' => $relationTable],
                        'relation.parent_id = queue.queue_id',
                        [
                            'composite_status' => new Zend_Db_Expr('\'new\''),
                            'sync_attempt' => new Zend_Db_Expr(0),
                        ]
                    )
                    ->join(
                        ['old_queue' => $queueTable],
                        'relation.queue_id = old_queue.queue_id',
                        []
                    )
                    ->where(
                        'queue.composite_status = \'error\''
                    )
                    ->where(
                        implode(' OR ', $orWhere)
                    );
                $query = $connection->updateFromSelect($select, ['queue' => $queueTable]);
                $connection->query($query);
            }
        }
    }
}
