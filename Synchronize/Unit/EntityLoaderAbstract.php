<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Synchronize\Unit;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use TNW\Salesforce\Api\CleanableInstanceInterface;
use TNW\Salesforce\Model\Entity\SalesforceIdStorage;

/**
 * Mapping Entity Loader
 */
abstract class EntityLoaderAbstract
{
    /**
     * @var SalesforceIdStorage
     */
    protected $salesforceIdStorage;

    /**
     * MappingEntityLoaderAbstract constructor.
     *
     * @param SalesforceIdStorage|null $salesforceIdStorage
     */
    public function __construct(
        ?SalesforceIdStorage $salesforceIdStorage = null
    ) {
        $this->salesforceIdStorage = $salesforceIdStorage;
    }

    /**
     * Load
     *
     * @param AbstractModel $entity
     * @return AbstractModel
     * @throws LocalizedException
     */
    public function get($entity)
    {
        $subEntity = $this->load($entity);
        if ($subEntity && null !== $this->salesforceIdStorage && null !== $subEntity->getId()) {
            $this->salesforceIdStorage->load($subEntity, $entity->getData('config_website'));
        }

        return $subEntity;
    }

    /**
     * @return SalesforceIdStorage|null
     */
    public function getSalesforceIdStorage()
    {
        return $this->salesforceIdStorage;
    }

    /**
     * @param array $entities
     *
     * @return void
     * @throws LocalizedException
     */
    public function preloadSalesforceIds(array $entities): void
    {
        if (null !== $this->salesforceIdStorage) {
            $this->salesforceIdStorage->massLoadObjectIds($entities);
        }
    }

    /**
     * Load
     *
     * @param AbstractModel $entity
     * @return AbstractModel|null
     */
    abstract public function load($entity);
}
