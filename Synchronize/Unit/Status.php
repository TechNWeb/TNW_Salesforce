<?php

namespace TNW\Salesforce\Synchronize\Unit;

use Magento\Framework\Exception\LocalizedException;
use OutOfBoundsException;
use TNW\Salesforce\Model\Entity\SalesforceIdStorage;
use TNW\Salesforce\Model\Queue;
use TNW\Salesforce\Synchronize;

/**
 * Unit Status
 */
class Status extends Synchronize\Unit\UnitAbstract
{
    /**
     * @var string
     */
    private $load;

    /**
     * @var string
     */
    private $upsertOutput;

    /**
     * @var SalesforceIdStorage|null
     */
    private $salesforceIdStorage;

    /**
     * Status constructor.
     * @param string $name
     * @param string $load
     * @param string $upsertOutput
     * @param Synchronize\Units $units
     * @param Synchronize\Group $group
     * @param SalesforceIdStorage $salesforceIdStorage
     * @param array $dependents
     */
    public function __construct(
        $name,
        $load,
        $upsertOutput,
        Synchronize\Units $units,
        Synchronize\Group $group,
        SalesforceIdStorage $salesforceIdStorage = null,
        array $dependents = []
    ) {
        parent::__construct($name, $units, $group, array_merge($dependents, [$load, $upsertOutput]));
        $this->load = $load;
        $this->upsertOutput = $upsertOutput;
        $this->salesforceIdStorage = $salesforceIdStorage;
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
            switch (true) {
                case !empty($this->getAllEntityError($entity)):
                    $this->cache[$entity]['status'] = Queue::STATUS_ERROR;
                    $this->cache[$entity]['message'] = implode("<br />\n", $this->getAllEntityError($entity));
                    break;

                case $upsertOutput->get('%s', $entity) === null:
                    $this->cache[$entity]['status'] = Queue::STATUS_SKIPPED;
                    break;

                case $upsertOutput->get('%s/skipped', $entity) === true:
                    $this->cache[$entity]['status'] = $upsertOutput->upsertInput()->get('%s/updated', $entity) ? Queue::STATUS_COMPLETE : Queue::STATUS_SKIPPED;
                    $this->cache[$entity]['message'] = $upsertOutput->upsertInput()->get('%s/message', $entity);
                    break;

                case $upsertOutput->get('%s/waiting', $entity) === true:
                    $this->cache[$entity]['status'] = Queue::STATUS_WAITING_UPSERT;
                    $this->cache[$entity]['message'] = $upsertOutput->upsertInput()->get('%s/message', $entity);
                    break;

                case $upsertOutput->get('%s/success', $entity) === true:

                    $this->cache[$entity]['status'] = Queue::STATUS_COMPLETE;
                    $this->cache[$entity]['message'] = $upsertOutput->upsertInput()->get('%s/message', $entity);
                    break;

                default:

                    $this->cache[$entity]['status'] = Queue::STATUS_ERROR;
                    $this->cache[$entity]['message'] = $upsertOutput->get('%s/message', $entity);
                    break;
            }

            $this->saveStatus($entity);
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
                    $this->salesforceIdStorage->saveStatus($entity, 1, $entity->getData('config_website'));
                    break;

                case Queue::STATUS_ERROR:
                    $this->salesforceIdStorage->saveStatus($entity, 0, $entity->getData('config_website'));
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
    protected function entities()
    {
        return $this->load()->get('entities');
    }
}
