<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Unit\Website\Website;

use Magento\Framework\Exception\LocalizedException;
use TNW\Salesforce\Synchronize;
use TNW\Salesforce\Synchronize\Group;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\InputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\Website\InputFactory as WebsiteInputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\OutputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\QueryInterface;
use TNW\Salesforce\Synchronize\Unit\IdentificationInterface;
use TNW\Salesforce\Synchronize\Units;

/**
 * Lookup
 *
 * @method \Magento\Store\Model\Website[] entities()
 */
class Lookup extends Synchronize\Unit\LookupAbstract
{
    /** @var WebsiteInputFactory  */
    protected $websiteInputFactory;

    public function __construct(
        $name,
        $load,
        Units $units,
        Group $group,
        IdentificationInterface $identification,
        InputFactory $inputFactory,
        OutputFactory $outputFactory,
        QueryInterface $process,
        WebsiteInputFactory $websiteInputFactory,
        array $dependents = [],
        $skipMappingFields = false
    ) {
        $this->websiteInputFactory = $websiteInputFactory;

        parent::__construct(
            $name,
            $load,
            $units,
            $group,
            $identification,
            $inputFactory,
            $outputFactory,
            $process,
            $dependents,
            $skipMappingFields
        );
    }

    /**
     * ProcessInput
     */
    public function processInput()
    {
        $codeField = 'tnw_mage_basic__Code__c';
        $magentoIdField = 'tnw_mage_basic__Website_ID__c';

        $this->input->columns[] = 'Id';
        $this->input->columns[] = $codeField;
        $this->input->columns[] = 'Name';
        $this->input->columns[] = $magentoIdField;
        $this->input->columns[] = 'OwnerId';

        foreach ($this->entities() as $entity) {
            $cacheObject = $this->getCacheObject();
            $this->input[$cacheObject]['OR'][$codeField]['IN'][] = $entity->getCode();
            $this->input[$cacheObject]['OR'][$magentoIdField]['IN'][] = (int)$entity->getId();
        }

        $this->input->from = 'tnw_mage_basic__Magento_Website__c';
    }

    /**
     * Process
     *
     * @throws LocalizedException
     */
    public function process()
    {
        $this->input = $this->websiteInputFactory->create();
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
     * Collect Index
     *
     * @return array
     */
    public function collectIndex()
    {
        $codeField = 'tnw_mage_basic__Code__c';
        $magentoIdField = 'tnw_mage_basic__Website_ID__c';

        $searchIndex = [];
        foreach ($this->output as $key => $record) {
            if (!empty($record[$magentoIdField])) {
                $searchIndex['magentoId'][$key] = (int)$record[$magentoIdField];
            }
            if (!empty($record[$codeField])) {
                $searchIndex['code'][$key] = strtolower((string)$record[$codeField]);
            }
        }

        return $searchIndex;
    }

    /**
     * Search Priority Order
     *
     * @param array $searchIndex
     * @param \Magento\Store\Model\Website $entity
     * @return array
     */
    public function searchPriorityOrder(array $searchIndex, $entity)
    {
        $recordsIds = [];
        if (!empty($searchIndex['magentoId'])) {
            // Priority 1
            $recordsIds[5] = array_keys($searchIndex['magentoId'], (int)$entity->getId());
        }
        if (!empty($searchIndex['code'])) {
            // Priority 2
            $recordsIds[10] = array_keys($searchIndex['code'], strtolower((string)$entity->getCode()));
        }

        return $recordsIds;
    }
}
