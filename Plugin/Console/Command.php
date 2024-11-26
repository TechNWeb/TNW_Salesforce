<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Plugin\Console;

use Symfony\Component\Console\Command\Command as Subject;
use TNW\Salesforce\Service\Sync\Entities as SyncEntitiesService;

class Command
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
     * @param Subject $subject
     * @param array $args
     * @return array
     */
    public function beforeRun(Subject $subject, ...$args)
    {
        $commandName = $subject->getName();

        if ($commandName !== 'cron:run') {
            $this->syncEntitiesService->execute();
        }

        return $args;
    }
}
