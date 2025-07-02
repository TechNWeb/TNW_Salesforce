<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Synchronize\Unit\Loader;

use Magento\Framework\Exception\LocalizedException;

/**
 * Mapping Entity Loader
 */
abstract class EntityAbstract implements \TNW\Salesforce\Synchronize\Unit\LoadLoaderInterface
{
    /**
     * @var \TNW\Salesforce\Model\Entity\SalesforceIdStorage
     */
    private $salesforceIdStorage;

    /**
     * MappingEntityLoaderAbstract constructor.
     * @param \TNW\Salesforce\Model\Entity\SalesforceIdStorage|null $salesforceIdStorage
     */
    public function __construct(
        ?\TNW\Salesforce\Model\Entity\SalesforceIdStorage $salesforceIdStorage = null
    ) {
        $this->salesforceIdStorage = $salesforceIdStorage;
    }

    /**
     * loadSalesforceId
     *
     * @param \Magento\Framework\Model\AbstractModel $entity
     * @return \Magento\Framework\Model\AbstractModel
     * @throws LocalizedException
     */
    public function loadSalesforceId($entity)
    {
        if (null !== $this->salesforceIdStorage && $entity && null !== $entity->getId()) {
            $this->salesforceIdStorage->load($entity, $entity->getData('config_website'));
        }

        return $entity;
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
}
