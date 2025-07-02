<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Api\Model\Grid;

interface UpdateByDataInterface
{
    /**
     * @param array|null $entityIds
     *
     * @return void
     */
    public function execute(?array $entityIds = null): void;
}
