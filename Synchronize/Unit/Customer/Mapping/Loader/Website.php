<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Unit\Customer\Mapping\Loader;

use TNW\Salesforce\Model\Entity\SalesforceIdStorage;
use TNW\Salesforce\Service\Synchronize\Unit\Load\SubEntities\Load;
use TNW\Salesforce\Synchronize\Unit\EntityLoaderAbstract;
use TNW\Salesforce\Synchronize\Unit\Load\EntityLoader\EntityPreLoaderInterface;

/**
 * Mapping Loader Website
 */
class Website extends EntityLoaderAbstract implements EntityPreLoaderInterface
{
    /** @var Load */
    private $loadSubEntities;

    /** @var array */
    private $loaders;

    /** @var array */
    private $afterLoadExecutors;

    /**
     * Website constructor.
     *
     * @param Load                     $loadSubEntities
     * @param array                    $loaders
     * @param array                    $afterLoadExecutors
     * @param SalesforceIdStorage|null $salesforceIdStorage
     */
    public function __construct(
        Load                $loadSubEntities,
        array               $loaders = [],
        array               $afterLoadExecutors = [],
        ?SalesforceIdStorage $salesforceIdStorage = null
    ) {
        parent::__construct($salesforceIdStorage);
        $this->loadSubEntities = $loadSubEntities;
        $this->loaders = $loaders;
        $this->afterLoadExecutors = $afterLoadExecutors;
    }

    /**
     * @inheritDoc
     */
    public function load($entity)
    {
        $entityId = spl_object_hash($entity);

        return $this->loadSubEntities->execute($this, [$entity])[$entityId] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getLoaders(): array
    {
        return $this->loaders;
    }

    /**
     * @inheritDoc
     */
    public function getAfterPreloadExecutors(): array
    {
        return $this->afterLoadExecutors;
    }
}
