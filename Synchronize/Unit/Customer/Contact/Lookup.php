<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Unit\Customer\Contact;

use TNW\Salesforce\Synchronize;

/**
 * Contact Lookup
 *
 * @method \Magento\Customer\Model\Customer[] entities()
 */
class Lookup extends Synchronize\Unit\LookupAbstract
{
    /**
     * @var \Magento\Customer\Model\Config\Share
     */
    protected $customerConfigShare;

    /**
     * Lookup constructor.
     *
     * @param string                                          $name
     * @param string                                          $load
     * @param Synchronize\Units                               $units
     * @param Synchronize\Group                               $group
     * @param Synchronize\Unit\IdentificationInterface        $identification
     * @param Synchronize\Transport\Calls\Query\InputFactory  $inputFactory
     * @param Synchronize\Transport\Calls\Query\OutputFactory $outputFactory
     * @param Synchronize\Transport\Calls\QueryInterface      $process
     * @param \Magento\Customer\Model\Config\Share            $customerConfigShare
     * @param array                                           $dependents
     */
    public function __construct(
        $name,
        $load,
        Synchronize\Units $units,
        Synchronize\Group $group,
        Synchronize\Unit\IdentificationInterface $identification,
        Synchronize\Transport\Calls\Query\InputFactory $inputFactory,
        Synchronize\Transport\Calls\Query\OutputFactory $outputFactory,
        Synchronize\Transport\Calls\QueryInterface $process,
        \Magento\Customer\Model\Config\Share $customerConfigShare,
        array $dependents = []
    ) {
        parent::__construct(
            $name,
            $load,
            $units,
            $group,
            $identification,
            $inputFactory,
            $outputFactory,
            $process,
            $dependents
        );

        $this->customerConfigShare = $customerConfigShare;
    }


    /**
     * Process Input
     *
     * @return bool
     */
    public function processInput()
    {
        $magentoIdField = 'tnw_mage_basic__Magento_ID__c';
        $websiteField = 'tnw_mage_basic__Magento_Website__c';

        $this->input->columns[] = 'ID';
        $this->input->columns[] = 'AccountId';
        $this->input->columns[] = 'FirstName';
        $this->input->columns[] = 'LastName';
        $this->input->columns[] = 'Email';
        $this->input->columns[] = 'OwnerId';
        $this->input->columns[] = $magentoIdField;
        $this->input->columns[] = $websiteField;

        foreach ($this->entities() as $entity) {
            $cacheObject = $this->getCacheObject();
            $salesForceWebsiteId = '';
            if ($this->customerConfigShare->isWebsiteScope()) {
                $salesForceWebsiteId = (string)$this->load()->entityByType($entity, 'website')->getData('salesforce_id');
            }
            $salesForceWebsites = [''];
            $salesForceWebsiteId && $salesForceWebsites[] = $salesForceWebsiteId;

            $email = strtolower((string)$entity->getEmail());
            if (!empty($email)) {
                $this->input[$cacheObject]['AND']['Global']['AND'][$salesForceWebsiteId]['AND']['Email']['IN'][] = $email;
                foreach ($salesForceWebsites as $website) {
                    $this->input[$cacheObject]['AND']['Global']['AND'][$salesForceWebsiteId]['AND'][$websiteField]['IN'][] = $website;
                }
            }

            $magentoId = $entity->getId();
            if (!empty($magentoId)) {
                $this->input[$cacheObject]['AND']['Global']['OR'][$magentoIdField]['IN'][] = $magentoId;
            }
        }

        $this->input->from = 'Contact';
    }

    /**
     * Collect Index
     *
     * @return array
     */
    public function collectIndex()
    {
        $magentoIdField = 'tnw_mage_basic__Magento_ID__c';
        $magentoWebsiteField = 'tnw_mage_basic__Magento_Website__c';

        $searchIndex = [];
        foreach ($this->output as $key => $record) {
            $websiteId = '';
            if (!empty($record[$magentoWebsiteField]) && $this->customerConfigShare->isWebsiteScope()) {
                $websiteId = $record[$magentoWebsiteField];
            }

            if (!empty($record['Email'])) {
                $searchIndex['eaw'][$key] = strtolower((string)"{$record['Email']}:{$websiteId}");
            }

            if (!empty($record[$magentoIdField])) {
                $searchIndex['magentoId'][$key] = strtolower((string)$record[$magentoIdField]);
            }
        }

        return $searchIndex;
    }

    /**
     * Search Priority Order
     *
     * @param array                            $searchIndex
     * @param \Magento\Customer\Model\Customer $entity
     *
     * @return array
     */
    public function searchPriorityOrder(array $searchIndex, $entity)
    {
        $recordsIds = [];
        if (!empty($searchIndex['magentoId'])) {
            $recordsIds[10] = array_keys($searchIndex['magentoId'], strtolower((string)$entity->getId()));
        }

        if (!empty($searchIndex['eaw'])) {
            if ($this->customerConfigShare->isWebsiteScope()) {
                $websiteId = $this->load()->entityByType($entity, 'website')->getData('salesforce_id');
                $recordsIds[20] = array_keys($searchIndex['eaw'], strtolower((string)"{$entity->getEmail()}:{$websiteId}"));
            }

            $recordsIds[25] = array_keys($searchIndex['eaw'], strtolower((string)"{$entity->getEmail()}:"));
        }

        return $recordsIds;
    }
}
