<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace TNW\Salesforce\Service\Model\Grid;

use TNW\Salesforce\Api\CleanableInstanceInterface;
use TNW\Salesforce\Api\Model\Grid\GetColumnsDataItems\ExecutorInterface;
use TNW\Salesforce\Model\CleanLocalCache\CleanableObjectsList;

/**
 *  Class GetColumnsData
 */
class GetColumnsDataItems
{
    /** @var ExecutorInterface[] */
    private $executors;

    /** @var string */
    private $entityIdColumnName;

    /**
     * @param CleanableObjectsList $cleanableObjectsList
     * @param CleanableObjectsList $cleanableExecutorsList
     * @param array                $executors
     * @param string               $entityIdColumnName
     */
    public function __construct(
        CleanableObjectsList $cleanableObjectsList,
        CleanableObjectsList $cleanableExecutorsList,
        array                $executors,
        string               $entityIdColumnName = 'entity_id'
    ) {
        $this->executors = $executors;
        foreach ($executors as $executor) {
            if ($executor instanceof CleanableInstanceInterface) {
                $cleanableObjectsList->add($executor);
                $cleanableExecutorsList->add($executor);
            }
        }
        $this->entityIdColumnName = $entityIdColumnName;
    }


    /**
     * @param array|null $entityIds
     *
     * @return array
     */
    public function execute(?array $entityIds = null): array
    {
        $result = [];
        $nullableEntityIds = [];
        foreach ($this->executors as $columnName => $executor) {
            $items = $executor->execute($columnName, $entityIds);
            if ($entityIds === null) {
                $entityIds = array_keys($items);
            }
            foreach ($entityIds as $entityId) {
                $value = $items[$entityId] ?? null;
                if ($columnName === $this->entityIdColumnName && $value === null) {
                    $nullableEntityIds[] = $entityId;
                }
                $entityId && $result[$entityId][$columnName] = $value;
            }
        }

        foreach ($nullableEntityIds as $nullableEntityId) {
            unset($result[$nullableEntityId]);
        }

        return $result;
    }
}
