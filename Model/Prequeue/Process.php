<?php
declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Prequeue;

use Exception;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\DeadlockException;
use Magento\Framework\DB\Adapter\LockWaitException;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;
use TNW\Salesforce\Service\MessageQueue\CheckMemoryLimit;
use TNW\Salesforce\Api\MessageQueue\PublisherAdapter;
use TNW\Salesforce\Model\ResourceModel\PreQueue;
use TNW\Salesforce\Service\CleanLocalCacheForInstances;
use TNW\Salesforce\Service\Model\Prequeue\Process\GetAvailableSyncTypesByEntityTypes;
use TNW\Salesforce\Synchronize\Queue\Add;
use \TNW\Salesforce\Api\Model\Cron\Source\MagentoSyncTypeInterface;
use TNW\Salesforce\Api\Model\Synchronization\ConfigInterface;

class Process implements \TNW\Salesforce\Api\Model\Prequeue\ProcessInterface
{
    const MQ_TOPIC_NAME = 'tnw_salesforce.prequeue.process';

    /** @var Add[] */
    public $queueAddPool = [];

    /** @var PreQueue */
    protected $resourcePreQueue;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var TimezoneInterface
     */
    protected $timezone;

    /**
     * @var ConfigInterface
     */
    protected $config;

    /** @var PublisherAdapter */
    protected $publisher;

    /** @var MagentoSyncTypeInterface */
    protected $magentoSyncType;

    /** @var CleanLocalCacheForInstances */
    protected $cleanLocalCacheForInstances;

    /** @var GetAvailableSyncTypesByEntityTypes */
    protected $getAvailableSyncTypesByEntityTypes;

    /** @var CheckMemoryLimit  */
    protected $checkMemoryLimitService;
    /** @var LoggerInterface */
    protected $logger;

    /**
     * Process constructor.
     *
     * @param array $queueAddPool
     * @param PreQueue $resourcePreQueue
     * @param State $state
     * @param TimezoneInterface $timezone
     * @param ConfigInterface $config
     * @param PublisherAdapter $publisher
     * @param LoggerInterface $logger
     * @param MagentoSyncTypeInterface $magentoSyncType
     * @param CleanLocalCacheForInstances $cleanLocalCacheForInstances
     * @param CheckMemoryLimit $checkMemoryLimitService
     * @param GetAvailableSyncTypesByEntityTypes $getAvailableSyncTypesByEntityTypes
     */
    public function __construct(
        array                       $queueAddPool,
        PreQueue                    $resourcePreQueue,
        State                       $state,
        TimezoneInterface           $timezone,
        ConfigInterface                      $config,
        PublisherAdapter            $publisher,
        LoggerInterface             $logger,
        MagentoSyncTypeInterface    $magentoSyncType,
        CleanLocalCacheForInstances $cleanLocalCacheForInstances,
        CheckMemoryLimit $checkMemoryLimitService,
        GetAvailableSyncTypesByEntityTypes $getAvailableSyncTypesByEntityTypes
    ) {
        $this->queueAddPool = $queueAddPool;
        $this->resourcePreQueue = $resourcePreQueue;
        $this->state = $state;
        $this->timezone = $timezone;
        $this->config = $config;
        $this->publisher = $publisher;
        $this->magentoSyncType = $magentoSyncType;
        $this->cleanLocalCacheForInstances = $cleanLocalCacheForInstances;
        $this->getAvailableSyncTypesByEntityTypes = $getAvailableSyncTypesByEntityTypes;
        $this->checkMemoryLimitService = $checkMemoryLimitService;
        $this->logger = $logger;
    }

    /**
     * @return Select
     */
    public function getSelect()
    {
        return $this
            ->resourcePreQueue
            ->getConnection()
            ->select()
            ->from([$this->resourcePreQueue->getTable('tnw_salesforce_entity_prequeue')], ['entity_id'])
            ->where('entity_type = :entity_type')
            ->where('sync_type = :sync_type')
            ->order('entity_id')
            ->limit($this->config->getMaxItemsCountForQueue());
    }

    /**
     * @throws LocalizedException
     */
    public function markExecutionTime()
    {
        // save to config time when cron was executed
        $this->config->setGlobalLastCronRun(
            $this->config->getMagentoTime(),
            ConfigInterface::PRE_QUEUE_CRON
        );
    }

    /**
     * @param $entityType
     * @param $syncType
     *
     * @return array
     */
    public function getIdsBySyncType($entityType, $syncType)
    {
        $select = $this->getSelect();

        $entityIds = $this->resourcePreQueue
            ->getConnection()
            ->fetchCol(
                $select,
                [
                    'entity_type' => $entityType,
                    'sync_type' => $syncType,
                ]
            );

        return $entityIds;
    }

    /**
     * @param $entityIds
     * @param $queueAdd
     * @param $syncType
     * @param $entityType
     *
     * @throws LocalizedException
     */
    public function addEntitiesToQueue($entityIds, $queueAdd, $syncType, $entityType)
    {
        if (!empty($entityIds)) {
            foreach (array_chunk($entityIds, Add::DIRECT_ADD_TO_QUEUE_COUNT_LIMIT) as $ids) {
                $queueAdd->addToQueueDirectly($ids, $syncType);

                $this->deleteProcessedPrequeue($ids, $entityType, $syncType);
            }
        }
    }

    /**
     * @param $ids
     * @param $entityType
     * @param $syncType
     *
     * @throws LocalizedException
     */
    public function deleteProcessedPrequeue($ids, $entityType, $syncType)
    {
        $this
            ->resourcePreQueue
            ->getConnection()
            ->delete(
                $this->resourcePreQueue->getMainTable(),
                [
                    'entity_id IN (?)' => $ids,
                    'entity_type = ?' => $entityType,
                    'sync_type = ?' => $syncType
                ]
            );
    }

    /**
     * @param $entityType
     * @param $queueAdd
     *
     * @throws LocalizedException
     */
    public function processByPool($entityType, $queueAdd)
    {
        $maxAttempts = $this->config->getMaxAdditionalAttemptsCount(true);
        $availableSyncTypes = $this->getAvailableSyncTypesByEntityTypes->execute([$entityType])[$entityType] ?? [];

        $runNextBatch = false;
        foreach ($this->magentoSyncType->toOptionArray() as $syncType => $syncTypeText) {
            if (!isset($availableSyncTypes[$syncType])) {
                continue;
            }
            $entityIds = $this->getIdsBySyncType($entityType, $syncType);
            if (empty($entityIds)) {
                continue;
            }
            $runNextBatch = true;

            $countAttempts = 0;

            do {
                try {
                    $this->addEntitiesToQueue($entityIds, $queueAdd, $syncType, $entityType);
                    $countAttempts = 0;
                } catch (DeadlockException|LockWaitException $e) {
                    sleep(1); // If deadlock happens - DB is busy, wait for 1 second
                    $countAttempts++;
                    $phrase = __('DB Lock found, try add records to the Queue in next session. Code: %1', $e->getCode());
                    $message = implode(PHP_EOL, [$phrase, $e->getMessage(), $e->getTraceAsString()]);
                    $this->logger->critical($message);
                } catch (\Throwable $e) {
                    $countAttempts++;
                    $phrase = __('SalesForce adding entities to the queue caused an error: %1', $e->getMessage());
                    $message = implode(PHP_EOL, [$phrase, $e->getMessage(), $e->getTraceAsString()]);
                    $this->logger->critical($message);

                } finally {
                    if ($this->getQueueTypeCode($syncType)) {
                        if ($countAttempts == 0 || $countAttempts >= $maxAttempts) {
                            $this->deleteProcessedPrequeue($entityIds, $entityType, $syncType);
                        }

                        $this->publishMessage($syncType);
                    }
                }
            } while ($countAttempts != 0 && $countAttempts < $maxAttempts);

            $this->publisher->publish(Process::MQ_TOPIC_NAME, false);
            $this->afterAddToQueueAction();
        }

    }

    /**
     * @return void
     */
    public function afterAddToQueueAction()
    {
        $this->checkMemoryLimitService->execute();
    }

    /**
     * @param $syncType
     * @return void
     */
    public function publishMessage($syncType)
    {
        $topic = $this->getTopic($syncType);
        $this->publisher->publish($topic, $this->getQueueTypeCode($syncType));
    }

    /**
     * @param $syncType
     * @return string
     */
    public function getTopic($syncType)
    {
        return self::MQ_TOPIC_NAME;
    }

    /**
     * @param $syncType
     *
     * @return string|null
     */
    public function getQueueTypeCode($syncType)
    {
        $type = null;
        return $type;
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws Exception
     */
    public function execute(): void
    {
        $this->markExecutionTime();

        try {
            if (!$this->config->getSalesforceStatus()) {
                return;
            }

            $queueAddByEntityTypes = [];
            foreach ($this->queueAddPool as $item) {
                $entityType = $item->getEntityType();
                $queueAddByEntityTypes[$entityType] = $item;
            }
            $entityTypes = array_keys($queueAddByEntityTypes);
            $this->getAvailableSyncTypesByEntityTypes->execute($entityTypes);

            foreach ($queueAddByEntityTypes as $entityType => $queueAdd) {
                $this->processByPool($entityType, $queueAdd);
            }

        } catch (\Throwable $e) {
            $message = implode(PHP_EOL, [$e->getMessage(), $e->getTraceAsString()]);
            $this->logger->critical($message);
            throw new Exception(__('SalesForce attempt to process prequeue caused an error: ' . $e->getMessage()).toString());
        } finally {
            $this->cleanLocalCacheForInstances->execute();
        }
    }
}
