<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace TNW\Salesforce\Service\Model\Grid\Executor;

use Magento\Framework\Exception\LocalizedException;
use TNW\Salesforce\Api\ChunkSizeInterface;
use TNW\Salesforce\Api\CleanableInstanceInterface;
use TNW\Salesforce\Api\Model\Grid\GetColumnsDataItems\ExecutorInterface;
use TNW\Salesforce\Api\Service\Model\Grid\Executor\ByCollection\CreateCollectionInterface;

class GetColumnDataByCollection implements ExecutorInterface, CleanableInstanceInterface
{
    public const RESULT_TYPE_INT = 1;
    public const RESULT_TYPE_STRING = 2;
    public const RESULT_TYPE_BOOLEAN = 3;

    /** @var CreateCollectionInterface */
    private $createCollection;

    /** @var array */
    private $cache = [];

    /** @var array */
    private $processed = [];

    /** @var bool */
    private $fullProcessed = false;

    /** @var string|null */
    private $resultType;

    /** @var bool */
    private $allowNull;

    /**
     * @param CreateCollectionInterface $createCollection
     * @param string|null      $resultType
     * @param bool             $allowNull
     */
    public function __construct(
        CreateCollectionInterface $createCollection,
        ?string           $resultType = null,
        ?bool             $allowNull = true
    ) {
        $this->createCollection = $createCollection;
        $this->resultType = $resultType;
        $this->allowNull = $allowNull;
    }

    /**
     * @param string     $columnName
     *
     * @param array|null $entityIds
     *
     * @return array
     * @throws LocalizedException
     */
    public function execute(string $columnName, ?array $entityIds = null): array
    {
        if ($entityIds === null) {
            if (!$this->fullProcessed) {
                $collection = $this->createCollection->execute();
                foreach ($collection as $entity) {
                    $entityId = $entity->getId();
                    $this->processed[$entityId] = 1;
                    $entityId && $this->cache[$entityId] = $entity;

                }
                $this->fullProcessed = true;
            }

            $result = [];
            foreach ($this->cache as $entity) {
                $entityId = $entity->getId();
                $value = $entity->getData($columnName);
                $entityId && $result[$entityId] = $this->prepareValue($value);
            }

            return $result;
        }

        if (!$entityIds) {
            return [];
        }

        $entityIds = array_map('intval', $entityIds);
        $entityIds = array_unique($entityIds);

        $missedEntityIds = [];
        foreach ($entityIds as $entityId) {
            if (!isset($this->processed[$entityId])) {
                $missedEntityIds[] = $entityId;
                $this->cache[$entityId] = null;
                $this->processed[$entityId] = 1;
            }
        }

        if ($missedEntityIds) {
            foreach (array_chunk($missedEntityIds, ChunkSizeInterface::CHUNK_SIZE) as $missedEntityIdsChunk) {
                $collection = $this->createCollection->execute($missedEntityIdsChunk);
                foreach ($collection as $item) {
                    $entityId = $item->getId();
                    $entityId && $this->cache[$entityId] = $item;
                }
            }
        }

        $result = [];
        foreach ($entityIds as $entityId) {
            $entity = $this->cache[$entityId] ?? null;
            $value = $entity ? $entity->getData($columnName) : null;
            $entityId && $result[$entityId] = $this->prepareValue($value);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function clearLocalCache(): void
    {
        $this->cache = [];
        $this->processed = [];
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function prepareValue($value)
    {
        if ($this->resultType !== null) {
            if ($value === null && $this->allowNull) {
                return null;
            }

            switch ($this->resultType) {
                case self::RESULT_TYPE_INT:
                    $value = (int)$value;
                    break;
                case self::RESULT_TYPE_STRING:
                    $value = (string)$value;
                    break;
                case self::RESULT_TYPE_BOOLEAN:
                    $value = (bool)$value;
                    break;
            }
        }

        return $value;
    }
}
