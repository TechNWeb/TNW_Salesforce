<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace TNW\Salesforce\Service\Model\Grid\Executor\ByCollection;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use TNW\Salesforce\Api\Model\Grid\GetColumnsDataItems\Executor\ByCollection\CreateCollection\ModifierInterface;
use TNW\Salesforce\Api\Service\Model\Grid\Executor\ByCollection\CreateCollectionInterface;
use TNW\Salesforce\Service\Model\Grid\UpdateGridCollectionWithStatus;

class CreateCollection implements CreateCollectionInterface
{
    /** @var string */
    private $collectionClassName;

    /** @var ModifierInterface[] */
    private $modifiers;

    /** @var AbstractResource */
    private $resource;

    /** @var UpdateGridCollectionWithStatus */
    private $updateGridCollectionWithStatus;

    /** @var bool */
    private $addStatus;

    /**
     * @param AbstractResource               $resource
     * @param array                          $modifiers
     * @param string                         $collectionClassName
     * @param UpdateGridCollectionWithStatus $updateGridCollectionWithStatus
     * @param bool                           $addStatus
     */
    public function __construct(
        AbstractResource               $resource,
        array                          $modifiers,
        string                         $collectionClassName,
        UpdateGridCollectionWithStatus $updateGridCollectionWithStatus,
        bool                           $addStatus
    ) {
        $this->resource = $resource;
        $this->modifiers = $modifiers;
        $this->collectionClassName = $collectionClassName;
        $this->updateGridCollectionWithStatus = $updateGridCollectionWithStatus;
        $this->addStatus = $addStatus;
    }

    /**
     * @param array|null $entityIds
     *
     * @return Collection
     * @throws LocalizedException
     */
    public function execute(?array $entityIds = null): Collection
    {
        $collection = ObjectManager::getInstance()->create($this->collectionClassName);
        foreach ($this->modifiers as $modifier) {
            $modifier->execute($collection);
        }

        $tableAlias = 'main_table';
        $selectPartFrom = $collection->getSelect()->getPart(Select::FROM);

        foreach ($selectPartFrom as $alias => $data) {
            if (isset($data['joinType']) && $data['joinType'] == 'from') {
                $tableAlias = $alias;
            }
        }

        if ($tableAlias !== 'e') {
            $condition = $tableAlias . '.' . $this->resource->getIdFieldName();
        } else {
            $condition = $this->resource->getIdFieldName();
        }

        $entityIds !== null && $collection->addFieldToFilter(
            $condition,
            ['in' => $entityIds]
        );

        if ($this->addStatus) {
            $this->updateGridCollectionWithStatus->execute($collection);
        }

        return $collection;
    }
}
