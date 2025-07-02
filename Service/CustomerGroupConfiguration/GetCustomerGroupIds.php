<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Service\CustomerGroupConfiguration;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use TNW\Salesforce\Api\CleanableInstanceInterface;
use TNW\Salesforce\Model\Customer\Config;

/**
 *  Customer group id configurations
 */
class GetCustomerGroupIds implements CleanableInstanceInterface
{
    /** @var Config */
    private $customerConfig;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var array */
    private $cache = [];

    /**
     * @param Config                $customerConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Config $customerConfig, StoreManagerInterface $storeManager)
    {
        $this->customerConfig = $customerConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Get active customer group ids or null if all active
     *
     * @param int|null $websiteId
     *
     * @return array|null
     * @throws NoSuchEntityException
     */
    public function execute(?int $websiteId = null): ?array
    {
        $websiteId = $websiteId ?? $this->storeManager->getStore()->getWebsiteId();
        if (isset($this->cache[$websiteId])) {
            return $this->cache[$websiteId];
        }

        $useAllGroups = $this->customerConfig->getCustomerAllGroups($websiteId);
        $customerSyncGroupsIds = null;
        if (!$useAllGroups) {
            $customerSyncGroupsIds = $this->customerConfig->getCustomerSyncGroups($websiteId);
        }

        $this->cache[$websiteId] = $customerSyncGroupsIds;

        return $this->cache[$websiteId];
    }

    /**
     * @inheritDoc
     */
    public function clearLocalCache(): void
    {
        $this->cache = [];
    }
}
