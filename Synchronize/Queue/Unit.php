<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Queue;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use TNW\Salesforce\Api\Service\PreloaderEntityIdsInterface;
use TNW\Salesforce\Api\CleanableInstanceInterface;
use TNW\Salesforce\Model\Queue;
use TNW\Salesforce\Model\QueueFactory;
use TNW\Salesforce\Model\ResourceModel\Objects;
use TNW\Salesforce\Synchronize\Queue\Skip\PreloadQueuesDataInterface;
use TNW\Salesforce\Synchronize\Queue\Unit\CreateQueue\UnsetPendingStatusPool;

/**
 * Sync Unit
 */
class Unit implements CleanableInstanceInterface
{
    public const PRODUCT_PRICE_BOOK_ENTRY = 'productPricebookEntry';

    /**
     * @var string
     */
    private $code;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $entityType;

    /**
     * @var string
     */
    private $objectType;

    /**
     * @var CreateInterface[]
     */
    private $generators;

    /**
     * @var QueueFactory
     */
    private $queueFactory;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var string[]
     */
    private $children;

    /**
     * @var string[]
     */
    private $parents;

    /** @var SkipInterface[]  */
    private $skipRules;

    /**
     * @var bool
     */
    private $ignoreFindGeneratorException;

    /** @var array  */
    private $queuesByUnitCodeAndBaseEntityId = [];

    /**
     * @var Objects
     */
    protected $resourceObjects;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /** @var PreloaderEntityIdsInterface[] */
    private $preLoaders;

    /** @var UnsetPendingStatusPool */
    private $pendingStatusPool;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Queue constructor.
     *
     * @param string                   $code
     * @param string                   $description
     * @param string                   $entityType
     * @param string                   $objectType
     * @param array                    $generators
     * @param QueueFactory             $queueFactory
     * @param ObjectManagerInterface   $objectManager
     * @param Objects                  $resourceObjects
     * @param StoreManagerInterface    $storeManager
     * @param RequestInterface         $request
     * @param UnsetPendingStatusPool   $pendingStatusPool
     * @param LoggerInterface          $logger
     * @param array                    $skipRules
     * @param array                    $parents
     * @param array                    $children
     * @param array                    $preLoaders
     * @param bool                     $ignoreFindGeneratorException
     * @param SerializerInterface|null $serializer
     */
    public function __construct(
        $code,
        $description,
        $entityType,
        $objectType,
        array $generators,
        QueueFactory $queueFactory,
        ObjectManagerInterface $objectManager,
        Objects $resourceObjects,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        UnsetPendingStatusPool $pendingStatusPool,
        LoggerInterface $logger,
        array $skipRules = [],
        array $parents = [],
        array $children = [],
        array $preLoaders = [],
        bool $ignoreFindGeneratorException = false,
        ?SerializerInterface $serializer= null
    ) {
        $this->code = $code;
        $this->entityType = $entityType;
        $this->objectType = $objectType;
        $this->description = $description;
        $this->generators = $generators;
        $this->queueFactory = $queueFactory;
        $this->objectManager = $objectManager;
        $this->resourceObjects = $resourceObjects;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->skipRules = $skipRules;
        $this->parents = $parents;
        $this->children = $children;
        $this->ignoreFindGeneratorException = $ignoreFindGeneratorException;
        $this->serializer = $serializer ?? $objectManager->get(SerializerInterface::class);
        $this->preLoaders = $preLoaders;
        $this->pendingStatusPool = $pendingStatusPool;
        $this->logger = $logger;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function code()
    {
        return $this->code;
    }

    /**
     * Get entity type
     *
     * @return string
     */
    public function entityType()
    {
        return $this->entityType;
    }

    /**
     * Get object type
     *
     * @return string
     */
    public function objectType()
    {
        return $this->objectType;
    }

    /**
     * Get parents
     *
     * @return Unit[]
     */
    public function parents()
    {
        return array_map([$this->objectManager, 'get'], $this->parents);
    }

    /**
     * Get children
     *
     * @return Unit[]
     */
    public function children()
    {
        return array_map([$this->objectManager, 'get'], $this->children);
    }

    /**
     * Create
     *
     * @param string $loadBy
     * @param int    $entityId
     * @param int    $baseEntityId
     * @param array  $identifiers
     * @param array  $additionalLoad
     * @param array  $additionalToHash
     *
     * @return Queue
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function createQueue(
        $loadBy,
        $entityId,
        $baseEntityId,
        array $identifiers,
        array $additionalLoad = [],
        array $additionalToHash = [],
        int $priority = 0
    ) {
        $uniqueHash = hash('sha256', (sprintf(
            '%s/%s/%s/%s/%s/%s/%s',
            $this->code(),
            $this->entityType,
            $entityId,
            $this->serializer->serialize($additionalLoad),
            $this->objectType,
            $this->serializer->serialize($additionalToHash),
            $loadBy
        )));

        return $this->queueFactory->create(['data' => [
            'queue_id' => uniqid('', true),
            'code' => $this->code,
            'description' => $this->description($identifiers),
            'entity_type' => $this->entityType,
            'entity_id' => $entityId,
            'entity_load' => $loadBy,
            'entity_load_additional' => $additionalLoad,
            'object_type' => $this->objectType,
            'status' => 'new',
            'transaction_uid' => '0',
            'sync_attempt' => 0,
            '_base_entity_id' => [$baseEntityId],
            'identify' => $uniqueHash,
            'priority' => $priority,
            Queue::UNIQUE_HASH => $uniqueHash
        ]]);
    }

    /**
     * Prepare Description
     *
     * @param array $identifiers
     * @return string
     */
    public function description(array $identifiers)
    {
        $search = $replace = [];
        foreach ($identifiers as $key => $identifier) {
            $search[] = "{{identifier|$key}}";
            $replace[] = $identifier;
        }

        return str_replace($search, $replace, (string)$this->description);
    }

    /**
     * Get generator
     *
     * @param string $loadBy
     * @param int[] $entityIds
     * @param array $additional
     * @param int $websiteId
     * @return Queue[]
     * @throws LocalizedException
     */
    public function generateQueues($loadBy, $entityIds, array $additional, $websiteId, $relatedUnitCode)
    {
        $generator = $this->findGenerator($loadBy);
        if ($generator instanceof CreateInterface) {
            $preLoaders = $this->preLoaders[$loadBy] ?? [];
            $preLoaders && array_map(static function ($preLoader) use ($entityIds) {
                if ($preLoader instanceof PreloaderEntityIdsInterface) {
                    $preLoader->execute($entityIds);
                }
            }, $preLoaders);

            $queues = $generator->process($entityIds, $additional, [$this, 'createQueue'], $websiteId);

            $queues = $this->skipQueues($queues);

            if (!empty($queues)) {
                $queues = $this->correctBaseEntityId($queues, $relatedUnitCode);

                $queues = $this->mergeQueue($queues);
            }
            return $queues;
        }

        if ($this->ignoreFindGeneratorException) {
            return [];
        }

        throw new LocalizedException(__(
            'Unknown %3 generating method for %1 entity. Queue code %2',
            $this->entityType,
            $this->code,
            $loadBy
        ));
    }

    /**
     * @param $queues
     * @param $createBy
     */
    public function correctBaseEntityId($queues, $relatedUnitCode)
    {
        foreach ($queues as $queue) {
            $queue->setData(
                '_base_entity_id',
                [$relatedUnitCode => $queue->getData('_base_entity_id')]
            );
        }
        return $queues;
    }

    /**
     * @param $queues
     * @return array
     */
    public function mergeQueue(&$queues)
    {
        foreach ($queues as $i => $queue1) {
            foreach ($queues as $j => $queue2) {
                if ($j <= $i) {
                    continue;
                }

                if ($queue1->getData('identify') == $queue2->getData('identify')) {
                    $queue1 = $this->mergeQueueObjects($queue1, $queue2);
                    unset($queues[$j]);
                }
            }
        }

        return $queues;
    }

    /**
     * avoid items duplication
     * @param $unique
     * @param $queues
     * @return mixed
     */
    public function baseByUnique($unique, &$queues)
    {
        foreach ($unique as $i => $queue1) {
            foreach ($queues as $j => $queue2) {

                if ($queue1->getData('identify') == $queue2->getData('identify') && $queue1->getId() != $queue2->getId()) {
                    $queues[$j] = $this->mergeQueueObjects($queue1, $queue2);
                }
            }
        }

        return $queues;
    }

    /**
     * @param $queue1
     * @param $queue2
     * @return mixed
     */
    public function mergeQueueObjects($queue1, $queue2)
    {

        $_base_entity_ids = array_merge_recursive(
            $queue1->getData('_base_entity_id'),
            $queue2->getData('_base_entity_id')
        );

        foreach ($_base_entity_ids as $k => $_base_entity_id) {
            $_base_entity_ids[$k] = array_unique($_base_entity_id);
        }

        $queue1->setData('_base_entity_id', $_base_entity_ids);
        return $queue1;
    }

    /**
     * Find Generate
     *
     * @param string $type
     * @return CreateInterface|null
     */
    public function findGenerator($type)
    {
        foreach ($this->generators as $generator) {
            if (strcasecmp((string)$generator->createBy(), (string)$type) !== 0) {
                continue;
            }

            return $generator;
        }

        return null;
    }

    /**
     * @param string $unitCode
     *
     * @return array
     */
    public function getQueuesGroupedByBaseEntityIds(string $unitCode): array
    {
        return $this->queuesByUnitCodeAndBaseEntityId[$unitCode] ?? [];
    }

    /**
     * @param Queue[] $queues
     */
    public function addQueues(array $queues): void
    {
        foreach ($queues as $queue) {
            $identify = $queue->getIdentify();
            $baseEntityIdsByUnitCodes = $queue->getData('_base_entity_id') ?? [];
            $baseEntityIdsByUnitCodes = is_array($baseEntityIdsByUnitCodes) ? $baseEntityIdsByUnitCodes : [];
            foreach ($baseEntityIdsByUnitCodes as $unitCode => $baseEntityIds) {
                $baseEntityIds = is_array($baseEntityIds) ? $baseEntityIds : [];
                foreach ($baseEntityIds as $baseEntityId) {
                    $this->queuesByUnitCodeAndBaseEntityId[$unitCode][$baseEntityId][$identify] = $queue;
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function clearLocalCache(): void
    {
        $this->queuesByUnitCodeAndBaseEntityId = [];
    }

    /**
     * @param Queue[] $queues
     *
     * @return Queue[]
     * @throws NoSuchEntityException
     */
    private function skipQueues(array $queues): array
    {
        $filteredQueues = [];
        if (!$queues) {
            return $filteredQueues;
        }
        $storeId = (int)$this->request->getParam('store', 0);
        $store = $this->storeManager->getStore($storeId);
        $websiteId = (int)$store->getWebsiteId();
        $filteredQueues = $queues;
        foreach ($this->skipRules as $skipRule) {
            if ($skipRule instanceof PreloadQueuesDataInterface) {
                $skipRule->preload($filteredQueues);
            }

            foreach ($filteredQueues as $index => $queue) {
                $skipResult = $skipRule->apply($queue);
                if ($skipResult !== false) {
                    $entityId = (int)$queue->getEntityId();
                    $this->pendingStatusPool->addItem(
                        $this->objectType,
                        $this->entityType,
                        $websiteId,
                        $entityId
                    );
                    unset($filteredQueues[$index]);
                    $message = [
                        'Queue is skipped!',
                        'Skip rule class:',
                        get_class($skipRule),
                        'Entity id: ',
                        $queue->getEntityId(),
                        'Entity type: ',
                        $queue->getEntityType(),
                        'Entity load: ',
                        $queue->getEntityLoad(),
                        'Skip rule result: ',
                        $skipResult === true ? 'true' : (string)$skipResult
                    ];
                    $this->logger->debug(
                        implode(
                            PHP_EOL,
                            $message
                        )
                    );
                }
            }
        }

        return $filteredQueues;
    }
}
