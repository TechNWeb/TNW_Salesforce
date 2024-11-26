<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Service\Sync;

use Magento\Framework\Event\Manager;
use TNW\Salesforce\Model\Config;

/**
 * Run Sync Entities Dispatch Event
 */
class Entities
{
    /**
     * @var Manager
     */
    private $eventManager;

    /**
     * @var Config
     */
    private $salesforceConfig;

    /**
     * @param Manager $eventManager
     * @param Config $salesforceConfig
     */
    public function __construct(
        Manager $eventManager,
        Config $salesforceConfig
    ) {
        $this->eventManager = $eventManager;
        $this->salesforceConfig = $salesforceConfig;
    }

    /**
     * @return void
     */
    public function execute()
    {
        if ($this->salesforceConfig->getSalesforceStatus()) {
            return;
        }

        $this->eventManager->dispatch('tnw_salesforce_entities_sync');
    }
}
