<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Api\Service\Model\Grid\Executor\ByCollection;
use Magento\Framework\Data\Collection;

/**
 * Interface GetIdsFilteredByCustomerGroupConfigurationInterface
 */
interface CreateCollectionInterface
{

    /**
     * @param array|null $entityIds
     * @return Collection
     */
    public function execute(?array $entityIds = null): Collection;
}
