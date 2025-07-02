<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace TNW\Salesforce\Service\Synchronize\Unit\Load;

use Magento\Framework\Model\AbstractModel;
use Psr\Log\LoggerInterface;
use TNW\Salesforce\Api\ChunkSizeInterface;
use TNW\Salesforce\Api\CleanableInstanceInterface;
use TNW\Salesforce\Synchronize\Unit\Load\PreLoaderInterface;
use TNW\Salesforce\Synchronize\Unit\Loader\EntityAbstract;
use TNW\Salesforce\Synchronize\Unit\LoadLoaderInterface;

/**
 *  Class PreLoadEntities
 */
class PreLoadEntities implements CleanableInstanceInterface
{
    private const CHUNK_SIZE = ChunkSizeInterface::CHUNK_SIZE;

    /** @var array */
    private $cache = [];

    /** @var array */
    private $processed = [];

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @param LoadLoaderInterface $preLoader
     * @param array               $entityIds
     * @param array               $entityAdditional
     *
     * @return AbstractModel[]
     */
    public function execute(LoadLoaderInterface $preLoader, array $entityIds, array $entityAdditional = [], ?string $idFieldName = null): array
    {
        if (!$entityIds || !($preLoader instanceof PreLoaderInterface)) {
            return [];
        }

        try {
            $entityIds = array_map('intval', $entityIds);
            $entityIds = array_unique($entityIds);
            $type = spl_object_hash($preLoader);
            $missedEntityIds = [];
            foreach ($entityIds as $entityId) {
                $loadAdditional = $entityAdditional[$entityId] ?? [];
                $groupValue = $preLoader->getGroupValue($loadAdditional);
                if (!isset($this->processed[$type][$groupValue][$entityId])) {
                    $missedEntityIds[] = $entityId;
                    $this->processed[$type][$groupValue][$entityId] = 1;
                }
            }
            if ($missedEntityIds) {
                foreach (array_chunk($missedEntityIds, self::CHUNK_SIZE) as $missedEntityIdsChunk) {
                    $collection = $preLoader->createCollectionInstance();

                    $missedItems = $missedEntityAdditional = [];
                    if ($collection) {
                        if ($idFieldName === null) {
                            $idFieldName = $collection->getIdFieldName();
                            if (!$idFieldName) {
                                $idFieldName = $collection->getResource()->getIdFieldName();
                            }
                        }
                        $collection->addFieldToFilter(
                            $idFieldName,
                            ['in' => $missedEntityIdsChunk]
                        );
                        foreach ($collection as $item) {
                            $itemId = $item->getData($idFieldName);
                            $missedItems[$itemId] = $item;
                            $missedEntityAdditional[$itemId] = $entityAdditional[$itemId] ?? [];
                        }
                        foreach ($missedEntityIdsChunk as $missedEntityId) {
                            if (!isset($missedItems[$missedEntityId])) {
                                $missedItems[$missedEntityId] = $preLoader->createEmptyEntity();
                                $missedEntityAdditional[$missedEntityId] = $entityAdditional[$missedEntityId] ?? [];
                            }
                        }
                    } else {
                        foreach ($missedEntityIdsChunk as $itemId) {
                            $emptyEntity = $preLoader->createEmptyEntity();
                            $item = $emptyEntity->setId($itemId);
                            $missedItems[$itemId] = $item;
                            $missedEntityAdditional[$itemId] = $entityAdditional[$itemId] ?? [];
                        }
                    }
                    if ($preLoader instanceof EntityAbstract) {
                        $preLoader->preloadSalesforceIds($missedItems);
                    }
                    $afterPreLoadExecutors = $preLoader->getAfterPreLoadExecutors();
                    ksort($afterPreLoadExecutors);
                    foreach ($afterPreLoadExecutors as $afterLoadExecutor) {
                        $missedItems = $afterLoadExecutor->execute($missedItems, $missedEntityAdditional);
                    }
                    foreach ($missedItems as $itemId => $missedItem) {
                        if ($missedItem) {
                            $data = $missedItem->getData('preloadInfo') ?? [];
                            $data['loader'] = $preLoader;
                            $missedItem->setData('preloadInfo', $data);
                        }

                        $loadAdditional = $entityAdditional[$itemId] ?? [];
                        $groupValue = $preLoader->getGroupValue($loadAdditional);
                        $this->cache[$type][$groupValue][$itemId] = $missedItem;
                    }
                }
            }
            $result = [];
            foreach ($entityIds as $entityId) {
                $loadAdditional = $entityAdditional[$entityId] ?? [];
                $groupValue = $preLoader->getGroupValue($loadAdditional);
                $item = $this->cache[$type][$groupValue][$entityId] ?? $preLoader->createEmptyEntity()->setId($entityId);
                $item && $result[$entityId] = $item;
            }
        } catch (\Throwable $e) {
            $result = [];
            $message = implode(PHP_EOL, [$e->getMessage(), $e->getTraceAsString()]);
            $this->logger->critical($message);
        }


        return $result;
    }

    /**
     * @inheritDoc
     */
    public function clearLocalCache(): void
    {
        $this->processed = [];
        $this->cache = [];
    }
}
