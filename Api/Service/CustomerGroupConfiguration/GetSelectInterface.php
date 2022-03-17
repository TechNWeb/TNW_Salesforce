<?php

namespace TNW\Salesforce\Api\Service\CustomerGroupConfiguration;

use Magento\Framework\DB\Select;

/**
 * Interface GetSelectInterface
 */
interface GetSelectInterface
{
    /**
     * @param array $entityIds
     *
     * @return null|Select
     */
    public function execute(array $entityIds): ?Select;
}
