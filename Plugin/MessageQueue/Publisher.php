<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Plugin\MessageQueue;

use Magento\Framework\MessageQueue\PublisherInterface;
use TNW\Salesforce\Service\Sync\Entities as SyncEntitiesService;

class Publisher
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
     * @param PublisherInterface $subject
     * @param mixed $result
     * @param string $topicName
     * @param mixed $data
     * @return mixed
     */
    public function afterPublish(PublisherInterface $subject, $result, $topicName, $data)
    {
        $this->syncEntitiesService->execute();

        return $result;
    }
}
