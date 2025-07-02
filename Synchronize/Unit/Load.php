<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Unit;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use TNW\Salesforce\Model\Entity\SalesforceIdStorage;
use TNW\Salesforce\Model\Queue;
use TNW\Salesforce\Model\ResourceModel\Objects;
use TNW\Salesforce\Model\ResourceModel\Queue\GetDependenceQueueIdsGroupedByCode;
use TNW\Salesforce\Service\Model\ResourceModel\Objects\MassLoadObjectIds;
use TNW\Salesforce\Service\Model\ResourceModel\Queue\GetDependenceIdsByEntityType;
use TNW\Salesforce\Service\Model\ResourceModel\Queue\GetQueuesByIds;
use TNW\Salesforce\Service\Synchronize\Unit\Load\PreLoadEntities;
use TNW\Salesforce\Service\Synchronize\Unit\Load\SubEntities\Load as SubEntitiesLoad;
use TNW\Salesforce\Synchronize\Group;
use TNW\Salesforce\Synchronize\Unit\Load\EntityLoader\EntityPreLoaderInterface;
use TNW\Salesforce\Synchronize\Unit\Load\PreLoaderInterface;
use TNW\Salesforce\Synchronize\Units;

/**
 * Load
 */
class Load extends UnitAbstract
{
    /**
     * @var MassLoadObjectIds
     */
    protected $massLoadObjectIds;

    /**
     * @var string
     */
    private $magentoType;

    /**
     * @var Queue[]
     */
    protected $queues;

    /**
     * @var LoadLoaderInterface[]
     */
    private $loaders;

    /**
     * @var IdentificationInterface
     */
    protected $identification;

    /**
     * @var HashInterface
     */
    private $hash;

    /**
     * @var SalesforceIdStorage
     */
    private $entityObject;

    /**
     * @var EntityLoaderAbstract[]
     */
    private $entityLoaders;

    /**
     * @var array
     */
    private $entityTypeMapping;

    /**
     * @var Objects
     */
    protected $objects;

    /** @var PreLoadEntities */
    private $preLoadEntities;

    /** @var GetDependenceIdsByEntityType */
    private $getDependenceIdsByEntityType;

    /** @var GetQueuesByIds */
    private $getQueuesByIds;

    /** @var SubEntitiesLoad */
    private $loadSubEntities;

    /** @var GetDependenceQueueIdsGroupedByCode */
    private $getDependenceQueueIdsGroupedByCode;

    /**
     * Load constructor.
     *
     * @param string                       $name
     * @param string                       $magentoType
     * @param array $queues
     * @param array $loaders
     * @param Units $units
     * @param Group $group
     * @param IdentificationInterface $identification
     * @param HashInterface $hash
     * @param Objects $objects
     * @param PreLoadEntities $preLoadEntities
     * @param GetDependenceIdsByEntityType $getDependenceIdsByEntityType
     * @param GetQueuesByIds $getQueuesByIds
     * @param SubEntitiesLoad $loadSubEntities
     * @param GetDependenceQueueIdsGroupedByCode $getDependenceQueueIdsGroupedByCode
     * @param SalesforceIdStorage|null $entityObject
     * @param MassLoadObjectIds $massLoadObjectIds
     * @param array $entityLoaders
     * @param array $entityTypeMapping
     */
    public function __construct(
        $name,
        $magentoType,
        array $queues,
        array $loaders,
        Units                              $units,
        Group                              $group,
        IdentificationInterface            $identification,
        HashInterface                      $hash,
        Objects                            $objects,
        PreLoadEntities                    $preLoadEntities,
        GetDependenceIdsByEntityType       $getDependenceIdsByEntityType,
        GetQueuesByIds                     $getQueuesByIds,
        SubEntitiesLoad                    $loadSubEntities,
        GetDependenceQueueIdsGroupedByCode $getDependenceQueueIdsGroupedByCode,
        MassLoadObjectIds                  $massLoadObjectIds,
        ?SalesforceIdStorage               $entityObject = null,
        array                              $entityLoaders = [],
        array                              $entityTypeMapping = []
    ) {
        parent::__construct($name, $units, $group);
        $this->magentoType = $magentoType;
        $this->queues = $queues;
        $this->loaders = $loaders;
        $this->identification = $identification;
        $this->hash = $hash;
        $this->objects = $objects;
        $this->entityObject = $entityObject;
        $this->entityLoaders = $entityLoaders;
        $this->entityTypeMapping = $entityTypeMapping;
        $this->preLoadEntities = $preLoadEntities;
        $this->getDependenceIdsByEntityType = $getDependenceIdsByEntityType;
        $this->getQueuesByIds = $getQueuesByIds;
        $this->loadSubEntities = $loadSubEntities;
        $this->getDependenceQueueIdsGroupedByCode = $getDependenceQueueIdsGroupedByCode;
        $this->massLoadObjectIds = $massLoadObjectIds;
    }

    /**
     * Identification
     *
     * @return IdentificationInterface
     */
    public function identification()
    {
        return $this->identification;
    }

    /**
     * Description
     */
    public function description()
    {
        return __('Loading Magento %1 ...', $this->magentoType);
    }

    /**
     * Process
     */
    public function process()
    {
        $this->reset();
        $index = [];
        $i = 0;
        $this->preloadDependenceQueuesGroupedByCode();
        $this->preloadEntities();
        $this->preloadObjectIds();
        $this->preloadDependedQueues();

        foreach ($this->queues as $queue) {
            try {
                $i++;
                $this->group()->messageDebug('>>> Load Entity progress: %s of %s', $i, count($this->queues));
                $this->group()->messageDebug('>>> Load Entity %s', $this->description());
                $entity = $this->loadEntity($queue, $index);
                $this->group()->messageDebug('<<< Loaded Entity %s', $this->description());
                if (empty($entity)) {
                    $message[] = __('QueueId item %1 is not available anymore', $queue->getId());
                    continue;
                }

                $entity_id = null;
                $idFieldName = $entity->getIdFieldName();
                $idFieldName && $entity_id = $entity->getData($idFieldName);
                if (!isset($entity_id) && !$entity->getData('generated')) {
                    $syncDetails = __('The related Magento record is not available');
                    $message[] = $syncDetails;
                    $queue->setData('status', Queue::STATUS_SKIPPED);
                    $queue->setData('message', $syncDetails);
                    continue;
                }

                $entity->setData('_queue', $queue);

                $this->cache[$entity]['queue'] = $queue;

                foreach ($this->entityLoaders as $entityType => $entityLoader) {
                    $this->group()->messageDebug('>>> >>> Load SUB Entity: %s', get_class($entityLoader));
                    $subEntity = $entityLoader->get($entity);
                    $this->group()->messageDebug('<<< <<< Loaded SUB Entity: %s', get_class($entityLoader));

                    if (!empty($subEntity)) {
                        if (!empty($entityLoader->getSalesforceIdStorage()) && !$subEntity->getData($subEntity->getIdFieldName())) {
                            $salesforceIds = $this->massLoadObjectIds->loadObjectIds(
                                $queue->getEntityId(),
                                $queue->getEntityLoad(),
                                $queue->getWebsiteId()
                            );
                            if ($salesforceIds) {
                                foreach (
                                    $entityLoader->getSalesforceIdStorage()->getMappingAttribute()
                                    as $salesforceIdName => $salesforceType
                                ) {
                                    if (isset($salesforceIds[$salesforceType])) {
                                        $subEntity->addData([$salesforceIdName => $salesforceIds[$salesforceType]]);
                                    }
                                }

                            }
                        }

                        $additional = [];
                        $mappedEntityType = $this->entityTypeMap($entityType);
                        foreach ($this->getDependedQueues((string)$queue->getId(), (string)$mappedEntityType) as $_queue) {
                            if (
                                (!$subEntity->getGenerated() && $subEntity->getId() != $_queue->getEntityId())
                                || ($subEntity->getGenerated() && $subEntity->getGeneratedEntityId() != $_queue->getEntityId())
                            ) {
                                continue;
                            }
                            foreach ($_queue->getAdditional() as $type => $id) {
                                if (empty($id) && (string)$id != '0') {
                                    continue;
                                }
                                $additional[$type][$id] = $id;
                            }
                        }
                        $additional = array_map(function ($item) {
                            return is_array($item) ? implode("\n", $item) : $item;
                        }, $additional);
                        $subEntity->addData($additional);

                        $this->cache[$entity]['entities'][$entityType] = $subEntity;
                    }
                }

                if (null !== $this->entityObject && null !== $entity->getId()) {
                    $this->entityObject->load($entity, $entity->getData('config_website'));
                }

                $hash = $this->hash->calculateEntity($entity);
                if (isset($index[$hash])) {
                    $this->cache['duplicates'][$index[$hash]][] = $entity;
                    $entity = $index[$hash];
                }

                $index[$hash] = $this->cache['entities'][$entity] = $entity;
                $this->cache['websiteIds'][$entity] = $this->websiteId($entity);
                $message[] = __('Entity %1 loaded', $this->identification->printEntity($entity));
            } catch (Exception $e) {
                $syncDetails = sprintf(
                    'Magento entity loading error, queueId: %s. Error: %s',
                    $queue->getId(),
                    $e->getMessage()
                );
                $message[] = $syncDetails;
                $queue->setData('status', Queue::STATUS_ERROR);
                $queue->setData('message', $syncDetails);
            }
        }

        if (!empty($message)) {
            $this->group()->messageDebug(implode("\n", $message));
        }
    }

    /**
     * Website Id
     *
     * @param AbstractModel $entity
     *
     * @return int
     */
    public function websiteId($entity)
    {
        return $entity->getData('config_website');
    }

    /**
     * Entity Type Map
     *
     * @param string $entityType
     *
     * @return mixed
     */
    private function entityTypeMap($entityType)
    {
        if (empty($this->entityTypeMapping[$entityType])) {
            return $entityType;
        }

        return $this->entityTypeMapping[$entityType];
    }

    /**
     * Object By Entity Type
     *
     * @param AbstractModel $entity
     * @param string        $entityType
     *
     * @return AbstractModel|null
     */
    public function entityByType($entity, $entityType)
    {
        if (empty($this->cache[$entity]['entities'][$entityType])) {
            $this->group()->messageDebug('Undefined magento entity type %s', $entityType);

            return null;
        }

        return $this->cache[$entity]['entities'][$entityType];
    }

    /**
     * Load Entity
     *
     * @param Queue $queue
     * @param array $index
     *
     * @return AbstractModel
     * @throws LocalizedException
     */
    public function loadEntity($queue, $index)
    {
        $entity = $this->loaderBy($queue->getEntityLoad())
            ->load($queue->getEntityId(), $queue->getEntityLoadAdditional());

        if (!empty($entity)) {
            $entity->setData('config_website', $queue->getWebsiteId());
        }

        $hash = $this->hash->calculateEntity($entity);
        if (isset($index[$hash])) {
            $entity = clone $entity;
        }

        return $entity;
    }

    /**
     * Loader By
     *
     * @param string $type
     *
     * @return LoadLoaderInterface
     * @throws LocalizedException
     */
    private function loaderBy($type)
    {
        foreach ($this->loaders as $loader) {
            if (strcasecmp((string)$loader->loadBy(), $type) !== 0) {
                continue;
            }

            return $loader;
        }

        throw new LocalizedException(__('Unknown loader %1. Unit name %2', $type, $this->name()));
    }

    /**
     * Entities
     *
     * @return array
     */
    public function entities()
    {
        return $this->cache->get('entities');
    }

    /**
     * Skipped
     *
     * @param AbstractModel $entity
     *
     * @return bool
     */
    public function skipped($entity)
    {
        return empty($this->cache['entities'][$entity]);
    }

    /**
     * @return void
     */
    private function preloadObjectIds(): void
    {
        $groupedByWebsiteIds = [];
        $magentoTypeFromEntityObject = $this->entityObject ? $this->entityObject->getMagentoType() : null;
        foreach ($this->queues as $queue) {
            $websiteId = $queue->getWebsiteId();
            $magentoType = $queue->getEntityLoad();
            $entityId = (int)$queue->getEntityId();
            $groupedByWebsiteIds[$websiteId][$magentoType][$entityId] = $entityId;
            if ($magentoTypeFromEntityObject && (string)$magentoTypeFromEntityObject !== (string)$magentoType) {
                $groupedByWebsiteIds[$websiteId][$magentoTypeFromEntityObject][$entityId] = $entityId;
            }
        }

        if ($groupedByWebsiteIds) {
            foreach ($groupedByWebsiteIds as $websiteId => $groupedByMagentoTypes) {
                foreach ($groupedByMagentoTypes as $magentoType => $entityIds) {
                    $entityIds = array_values($entityIds);

                    $this->massLoadObjectIds->massLoadObjectIds(
                        $entityIds,
                        (string)$magentoType,
                        (int)$websiteId
                    );
                }
            }
        }
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    private function preloadEntities(): void
    {
        $queuesByEntityLoad = [];
        foreach ($this->queues as $queue) {
            $queuesByEntityLoad[$queue->getEntityLoad()][] = $queue;
        }
        foreach ($queuesByEntityLoad as $entityLoad => $queues) {
            $loader = $this->loaderBy($entityLoad);
            if ($loader instanceof PreLoaderInterface) {
                $groupedByCacheKey = [];
                foreach ($queues as $queue) {
                    $loadAdditional = $queue->getEntityLoadAdditional() ?? [];
                    $groupValue = $loader->getGroupValue($loadAdditional);
                    $entityId = (int)$queue->getEntityId();
                    $groupedByCacheKey[$groupValue]['entityIds'][] = $entityId;
                    $groupedByCacheKey[$groupValue]['entityLoadAdditional'][$entityId] = $loadAdditional;
                    $groupedByCacheKey[$groupValue]['queues'][$entityId] = $queue;
                }
                foreach ($groupedByCacheKey as $data) {
                    $entityIds = $data['entityIds'] ?? [];
                    $entityLoadAdditional = $data['entityLoadAdditional'] ?? [];
                    $groupedQueues = $data['queues'] ?? [];
                    $entities = $this->preLoadEntities->execute($loader, $entityIds, $entityLoadAdditional) ?? [];

                    foreach ($entities as $entityId => $entity) {
                        $queue = $groupedQueues[$entityId] ?? null;
                        $entity->setData('_queue', $queue);
                    }

                    foreach ($this->entityLoaders as $entityLoader) {
                        if ($entityLoader instanceof EntityPreLoaderInterface) {
                            $this->loadSubEntities->execute($entityLoader, $entities);
                            foreach ($entities as $entity) {
                                if ($entity) {
                                    $data = $entity->getData('preloadInfo') ?? [];
                                    $data['subEntityLoaders'][] = $entityLoader;
                                    $entity->setData('preloadInfo', $data);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @return void
     */
    private function preloadDependedQueues(): void
    {
        $entityTypes = array_keys($this->entityLoaders);
        $queueIds = [];
        if (!$entityTypes) {
            return;
        }
        foreach ($this->queues as $queue) {
            $queueIds[] = $queue->getId();
        }
        $dependedQueueIdByEntityType = [];
        foreach ($entityTypes as $entityType) {
            $entityType = $this->entityTypeMap($entityType);
            $dependedQueueIdByEntityType[$entityType] = $this->getDependenceIdsByEntityType->execute($queueIds, (string)$entityType);
        }
        $dependedQueueIdsToLoad = [];
        foreach ($dependedQueueIdByEntityType as $dependedIdsByParent) {
            foreach ($dependedIdsByParent as $dependedIds) {
                $dependedQueueIdsToLoad[] = $dependedIds;
            }
        }

        $dependedQueueIdsToLoad = array_merge([], ...$dependedQueueIdsToLoad);
        $this->getQueuesByIds->execute($dependedQueueIdsToLoad);
    }

    /**
     * @param string $queueId
     * @param string $entityType
     *
     * @return Queue[]
     */
    private function getDependedQueues(string $queueId, string $entityType)
    {
        $dependedQueueIds = $this->getDependenceIdsByEntityType->execute([$queueId], (string)$entityType)[$queueId] ?? [];

        return $this->getQueuesByIds->execute($dependedQueueIds);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    private function preloadDependenceQueuesGroupedByCode(): void
    {
        $queueIds = [];
        foreach ($this->queues as $queue) {
            $queueIds[] = $queue->getId();
        }
        $this->getDependenceQueueIdsGroupedByCode->execute($queueIds);
    }
}
