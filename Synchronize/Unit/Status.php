<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Unit;

use Magento\Framework\Exception\LocalizedException;
use OutOfBoundsException;
use TNW\Salesforce\Model\Config;
use TNW\Salesforce\Model\Entity\SalesforceIdStorage;
use TNW\Salesforce\Model\Queue;
use TNW\Salesforce\Synchronize;
use TNW\Salesforce\Synchronize\Group;
use TNW\Salesforce\Synchronize\Units;

/**
 * Unit Status
 */
class Status extends Synchronize\Unit\UnitAbstract
{
    /**
     * @var string
     */
    protected $load;

    /**
     * @var string
     */
    protected $upsertOutput;

    /**
     * @var SalesforceIdStorage|null
     */
    protected $salesforceIdStorage;

    /** @var Config  */
    protected $config;

    /**
     * Status constructor.
     *
     * @param string                   $name
     * @param string                   $load
     * @param string                   $upsertOutput
     * @param Units                    $units
     * @param Group                    $group
     * @param Config                   $config
     * @param SalesforceIdStorage|null $salesforceIdStorage
     * @param array                    $dependents
     */
    public function __construct(
        $name,
        $load,
        $upsertOutput,
        Synchronize\Units $units,
        Synchronize\Group $group,
        Config $config,
        ?SalesforceIdStorage $salesforceIdStorage = null,
        array $dependents = []
    ) {
        parent::__construct($name, $units, $group, array_merge($dependents, [$load, $upsertOutput]));
        $this->load = $load;
        $this->upsertOutput = $upsertOutput;
        $this->config = $config;
        $this->salesforceIdStorage = $salesforceIdStorage;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return !empty($this->salesforceIdStorage) ? $this->salesforceIdStorage->getConfig() : $this->config;
    }

    /**
     * @inheritdoc
     */
    public function description()
    {
        return __('Status queue ...');
    }

    /**
     * Process
     *
     * @throws LocalizedException
     */
    public function process()
    {
        $upsertOutput = $this->upsertOutput();
        foreach ($this->entities() as $entity) {
            $maxAdditionalAttemptsCount = $this->getConfig()->getMaxAdditionalAttemptsCount(true);
            switch (true) {
                case $maxAdditionalAttemptsCount == $this->load()->get('%s/queue', $entity)->getSyncAttempt()
                    && $upsertOutput->get('%s/waiting', $entity) === true:
                    $this->cache[$entity]['status'] = Queue::STATUS_ERROR;
                    $message = $this->load()->get('%s/queue', $entity)->getMessage();
                    if ($message) {
                        $this->cache[$entity]['message'] = $message;
                    } else {
                        $this->cache[$entity]['message'] = __('Sync attempts count exceeded');
                    }
                    break;

                case !empty($this->getAllEntityError($entity)):
                    $this->cache[$entity]['status'] = Queue::STATUS_ERROR;
                    $this->cache[$entity]['message'] = implode("<br />\n", $this->getAllEntityError($entity));
                    break;

                case $upsertOutput->get('%s', $entity) === null:
                    $this->cache[$entity]['status'] = Queue::STATUS_SKIPPED;
                    break;

                case $upsertOutput->get('%s/skipped', $entity) === true:
                    $this->cache[$entity]['status'] = $upsertOutput->upsertInput()->get('%s/updated', $entity) ? Queue::STATUS_COMPLETE : Queue::STATUS_SKIPPED;
                    $message = $this->upsertOutput()->upsertInput()->get('%s/message', $entity);
                    if ($message) {
                        $this->cache[$entity]['message'] = $message;
                    }
                    break;

                case $upsertOutput->get('%s/waiting', $entity) === true:
                    $this->cache[$entity]['status'] = Queue::STATUS_WAITING_UPSERT;
                    $message = $this->upsertOutput()->upsertInput()->get('%s/message', $entity);
                    if ($message) {
                        $this->cache[$entity]['message'] = $message;
                    }
                    break;

                case $upsertOutput->get('%s/success', $entity) === true:

                    $this->cache[$entity]['status'] = Queue::STATUS_COMPLETE;
                    $message = $this->upsertOutput()->upsertInput()->get('%s/message', $entity);
                    if ($message) {
                        $this->cache[$entity]['message'] = $message;
                    }
                    break;

                default:

                    $this->cache[$entity]['status'] = Queue::STATUS_ERROR;
                    $this->cache[$entity]['message'] = $upsertOutput->get('%s/message', $entity);
                    $this->cache[$entity]['status_code'] = $upsertOutput->get('%s/status_code', $entity);
                    break;
            }

            $this->saveStatus($entity);
        }

        if (null !== $this->salesforceIdStorage) {
            $this->salesforceIdStorage->saveStatusFromCache();
        }

        $this->updateQueue();
    }

    /**
     * @param $entity
     * @throws LocalizedException
     */
    public function saveStatus($entity)
    {
        if (null !== $this->salesforceIdStorage) {
            switch ($this->cache[$entity]['status']) {
                case Queue::STATUS_COMPLETE:
                    $this->salesforceIdStorage->addStatusToCacheForMassUpdate($entity, 1, $entity->getData('config_website'));
                    break;

                case Queue::STATUS_ERROR:
                    $this->salesforceIdStorage->addStatusToCacheForMassUpdate($entity, 0, $entity->getData('config_website'));
                    break;
            }
        }
    }

    /**
     *
     */
    public function updateQueue()
    {
        foreach ($this->entities() as $entity) {
            $this->load()->get('%s/queue', $entity)
                ->addData(iterator_to_array($this->cache[$entity]));

            foreach ((array)$this->load()->get('duplicates/%s', $entity) as $duplicate) {
                $this->load()->get('%s/queue', $duplicate)
                    ->addData(iterator_to_array($this->cache[$entity]));
            }
        }
    }

    /**
     * Load
     *
     * @return Load|UnitInterface
     */
    public function load()
    {
        return $this->unit($this->load);
    }

    /**
     * Upsert Output
     *
     * @return Upsert\Output|UnitInterface
     */
    public function upsertOutput()
    {
        return $this->unit($this->upsertOutput);
    }

    /**
     * Entities
     *
     * @return object[]
     * @throws OutOfBoundsException
     */
    public function entities()
    {
        $entities = $this->load()->get('entities') ?? [];
        return $entities;
    }
}
