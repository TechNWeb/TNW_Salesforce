<?php
declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\ResourceModel\Log\File\Synchronization\Grid;

use TNW\Salesforce\Model\Config;
use TNW\Salesforce\Model\ResourceModel\Log\File\Grid\Collection as BaseCollection;

/**
 * Salesforce synchronization log grid collection.
 */
class Collection extends BaseCollection
{
    /** @var string */
    protected $logDir = Config::SALESFORCE_LOG_DIRECTORY;
}
