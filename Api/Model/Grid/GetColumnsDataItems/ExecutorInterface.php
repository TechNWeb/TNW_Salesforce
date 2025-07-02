<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Api\Model\Grid\GetColumnsDataItems;

/**
 * Interface ExecutorInterface
 */
interface ExecutorInterface
{
    /**
     * @param string     $columnName
     *
     * @param array|null $entityIds
     *
     * @return array
     */
    public function execute(string $columnName, ?array $entityIds = null): array;
}
