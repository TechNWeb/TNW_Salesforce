<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Synchronize\Unit;

use InvalidArgumentException;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use OutOfBoundsException;
use TNW\Salesforce\Model\ResourceModel\Mapper\Collection;
use TNW\Salesforce\Synchronize\Group;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\Input as QueryInput;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\InputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\Output;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\OutputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\QueryInterface;
use TNW\Salesforce\Synchronize\Unit\Upsert\Input;
use TNW\Salesforce\Synchronize\Units;

/**
 * Lookup Abstract
 */
abstract class LookupAbstract extends UnitAbstract
{

    protected $_cacheIterator = 0;
    protected $_cachePageSize = 200;

    /**
     * @var string
     */
    protected $load;

    protected $skipMappingFields = false;

    /**
     * @var InputFactory
     */
    protected $inputFactory;

    /**
     * @var OutputFactory
     */
    protected $outputFactory;

    /**
     * @var QueryInput
     */
    protected $input;

    /**
     * @var Output
     */
    protected $output;

    /**
     * @var QueryInterface
     */
    protected $process;

    /**
     * @var IdentificationInterface
     */
    protected $identification;

    /** @var DataObject */
    private $emptyDataObject;

    /**
     * LookupAbstract constructor.
     * @param string $name
     * @param string $load
     * @param Units $units
     * @param Group $group
     * @param IdentificationInterface $identification
     * @param InputFactory $inputFactory
     * @param OutputFactory $outputFactory
     * @param QueryInterface $process
     * @param bool $skipMappingFields
     * @param array $dependents
     */
    public function __construct(
        $name,
        $load,
        Units $units,
        Group $group,
        IdentificationInterface $identification,
        InputFactory $inputFactory,
        OutputFactory $outputFactory,
        QueryInterface $process,
        array $dependents = [],
        $skipMappingFields = false
    ) {
        parent::__construct($name, $units, $group, array_merge($dependents, [$load]));

        $this->load = $load;
        $this->inputFactory = $inputFactory;
        $this->outputFactory = $outputFactory;
        $this->process = $process;
        $this->identification = $identification;
        $this->skipMappingFields = $skipMappingFields;
    }

    /**
     * @inheritdoc
     */
    public function description()
    {
        return __('Trying to locate entity ...');
    }

    /**
     * Input
     *
     * @return QueryInput
     */
    public function input()
    {
        return $this->input;
    }

    /**
     * Output
     *
     * @return Output
     */
    public function output()
    {
        return $this->output;
    }

    /**
     * Process
     *
     * @throws LocalizedException
     */
    public function process()
    {
        $this->input = $this->inputFactory->create();
        $this->output = $this->outputFactory->create();

        $this->processInput();
        $this->addMappingFieldsToSelect();

        if ($this->input->count() === 0) {
            $this->group()->messageDebug('Lookup skipped');
            return;
        }

        $this->group()->messageDebug("Query request:\n%s", $this->input);
        $this->process->process($this->input, $this->output);
        $this->group()->messageDebug("Query response:\n%s", $this->output);

        $this->processOutput();
    }


    /**
     * Process Input
     */
    abstract public function processInput();

    /**
     * @return UnitInterface
     */
    public function getMappingUnit()
    {
        return $this->unit('mapping');
    }

    /**
     * @throws LocalizedException
     */
    public function addMappingFieldsToSelect()
    {
        if ($this->skipMappingFields) {
            return;
        }

        /** @var Input $upsertInput */
        $upsertInput = $this->unit('upsertInput');
        $mapping = [];

        if ($this->getMappingUnit()) {

            /** emulate lookup complete to load Update/Upsert mapping */
            $this->unit('lookup')->forceStatus(self::COMPLETE);

            foreach ($this->entities() as $entity) {
                $entity->setForceUpdateOnly(true);

                /** @var Collection $mapping */
                $mapping = $this->getMappingUnit()->mappers($entity);

                $entity->setForceUpdateOnly(false);
                break;
            }

            /** stop lookup complete emulation */
            $this->unit('lookup')->restoreStatus();
        }

        $definedColumns = $this->input->columns;
        // TODO : change it to the compareIgnoreFields as defined for \TNW\Salesforce\Unit\Upsert\Input
        $definedColumns[] = 'tnw_mage_enterp__disableMagentoSync__c';

        $definedColumns = array_map('strtolower', $definedColumns);

        foreach ($mapping as $map) {

            /** check if field is correct, available */
            if ($upsertInput) {
                $fieldName = $map->getSalesforceAttributeName();
                $fieldProperty = $upsertInput->findFieldProperty($fieldName);
                if (!$upsertInput->checkFieldProperty($fieldProperty, $fieldName, ['Id' => true])) {
                    continue;
                }
            }

            if (!in_array(strtolower((string)$map->getSalesforceAttributeName()), $definedColumns)) {
                $this->input->columns[] = $map->getSalesforceAttributeName();
                $definedColumns[] = strtolower((string)$map->getSalesforceAttributeName());
            }
        }
    }

    /**
     * Process Output
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function processOutput()
    {
        $searchIndex = $this->collectIndex();
        foreach ($this->entities() as $entity) {
            $recordsPriority = $this->searchPriorityOrder($searchIndex, $entity);
            ksort($recordsPriority, SORT_NUMERIC);

            array_walk_recursive($recordsPriority, function (&$record) {
                $record = $this->output[$record];
            });

            if (count($recordsPriority) === 0) {
                continue;
            }

            $this->cache[$entity]['recordsPriority'] = $recordsPriority;
            $this->cache[$entity]['records'] = $this->mergeLookupResult($recordsPriority);

            $record = $this->filterByPriority($recordsPriority, $entity);
            if (empty($record)) {
                continue;
            }

            $this->cache[$entity]['record'] = $this->prepareRecord($record);
            $message[] = __(
                "Found %1 entity and the following data:\n%2",
                $this->identification->printEntity($entity),
                print_r($record, true)
            );
        }

        $this->cache['allRecords'] = iterator_to_array($this->output);
        if (!empty($message)) {
            $this->group()->messageDebug(implode("\n", $message));
        }
    }

    /**
     * @param $recordsPriority
     * @return array
     */
    public function mergeLookupResult($recordsPriority)
    {
        $result = [];
        foreach ($recordsPriority as $priorityKey => $records) {
            $records = array_map([$this, 'prepareRecord'], $records);
            foreach ($records as $record) {
                if (isset($record['Id'])) {
                    $result[$record['Id']] = $record;
                }
            }
        }
        $result = array_values($result);

        return  $result;
    }

    /**
     * Prepare Record
     *
     * @param array $record
     * @return array
     */
    public function prepareRecord(array $record)
    {
        return $record;
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
     * Entities
     *
     * @return AbstractModel[]
     */
    public function entities()
    {
        $entities = $this->load()->get('entities') ?? [];
        return array_filter($entities, [$this, 'filter']);
    }

    /**
     * Filter
     *
     * @param AbstractModel $entity
     * @return bool
     * @throws OutOfBoundsException
     */
    public function filter($entity)
    {
        return !in_array(true, array_map(function ($unit) use ($entity) {
            return $this->unit($unit)->skipped($entity);
        }, $this->dependents()), true);
    }

    /**
     * Collect Index
     *
     * @return array
     */
    abstract public function collectIndex();

    /**
     * Search PriorityOrder
     *
     * @param array $searchIndex
     * @param AbstractModel $entity
     * @return array
     */
    abstract public function searchPriorityOrder(array $searchIndex, $entity);

    /**
     * Filter By Priority
     *
     * @param array $recordsPriority
     * @param AbstractModel $entity
     * @return array
     */
    public function filterByPriority(array $recordsPriority, $entity)
    {
        $findRecord = null;
        foreach ($recordsPriority as $records) {
            foreach ($records as $record) {
                $findRecord = $record;
                break 2;
            }

            if (!empty($findRecord)) {
                break;
            }
        }

        return $findRecord;
    }

    /**
     * Skipped
     *
     * @param object $entity
     * @return bool
     */
    public function skipped($entity)
    {
        return false;
    }

    /**
     * @return DataObject
     */
    public function getCacheObject(): DataObject
    {
        $this->_cacheIterator++;
        if($this->emptyDataObject === null || $this->_cacheIterator % $this->_cachePageSize === 0) {
            $cachePage = intdiv($this->_cacheIterator, $this->_cachePageSize) + 1;
            $this->emptyDataObject = new DataObject(['page' => $cachePage]);
        }

        return $this->emptyDataObject;
    }
}
