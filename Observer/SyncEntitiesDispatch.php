<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace TNW\Salesforce\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use TNW\Salesforce\Service\Sync\Entities as SyncEntitiesService;

/**
 *  Dispatch sync entities at cron run
 */
class SyncEntitiesDispatch implements ObserverInterface
{
    /**
     * @var SyncEntitiesService
     */
    private $syncEntitiesService;

    /**
     * @param SyncEntitiesService $syncEntitiesService
     */
    public function __construct(
        SyncEntitiesService $syncEntitiesService
    ) {
        $this->syncEntitiesService = $syncEntitiesService;
    }

    /**
     * Execute
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
        $this->syncEntitiesService->execute();
    }
}
