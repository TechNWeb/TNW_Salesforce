<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Api\Service\Customer;

use Magento\Framework\Exception\LocalizedException;

/**
 * Is customer sync disabled service interface.
 */
interface IsSyncDisabledInterface
{
    /**
     * Return true if customer sync is disabled.
     *
     * @param int $customerId
     *
     * @return bool
     * @throws LocalizedException
     */
    public function execute(int $customerId): bool;
}
