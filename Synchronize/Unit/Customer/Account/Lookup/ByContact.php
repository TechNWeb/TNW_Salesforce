<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Unit\Customer\Account\Lookup;

use Magento\Customer\Model\Customer;
use TNW\Salesforce\Model\ResourceModel\Mapper\Collection;
use TNW\Salesforce\Synchronize\Unit\Customer\Contact\Lookup;
use TNW\Salesforce\Synchronize\Unit\Upsert\Input;

/**
 * Lookup By Contact
 *
 * @method Customer[] entities()
 */
class ByContact extends Lookup
{

    /**
     * ProcessInput
     */
    public function processInput()
    {
        $magentoIdField = 'tnw_mage_basic__Magento_ID__c';
        $websiteField = 'tnw_mage_basic__Magento_Website__c';

        $this->input->columns[] = 'Id';
        $this->input->columns[] = 'Email';
        $this->input->columns[] = $magentoIdField;
        $this->input->columns[] = $websiteField;
        $this->input->columns[] = 'Account.Id';
        $this->input->columns[] = 'Account.OwnerId';
        $this->input->columns[] = 'Account.Name';


        foreach ($this->entities() as $entity) {
            $cacheObject = $this->getCacheObject();
            $salesForceWebsiteId = '';
            if ($this->customerConfigShare->isWebsiteScope()) {
                $salesForceWebsiteId = (string)$this->load()->entityByType($entity, 'website')->getData('salesforce_id');
            }
            $salesForceWebsites = [''];
            $salesForceWebsiteId && $salesForceWebsites[] = $salesForceWebsiteId;
            foreach ($salesForceWebsites as $website) {
                $this->input[$cacheObject]['AND']['Global']['AND'][$salesForceWebsiteId]['AND'][$websiteField]['IN'][] = $website;
            }
            $email = strtolower((string)$entity->getEmail());
            $email && $this->input[$cacheObject]['AND']['Global']['AND'][$salesForceWebsiteId]['AND']['Email']['IN'][] = $email;

            $magentoId = $entity->getId();
            if (!empty($magentoId)) {
                $this->input[$cacheObject]['AND']['Global']['OR'][$magentoIdField]['IN'][] = $magentoId;
            }

            $this->input[$cacheObject]['AND']['AccountId']['!='] = '';
        }

        $this->input->from = 'Contact';
    }

    /**
     * Prepare Record
     *
     * @param array $record
     *
     * @return mixed
     */
    public function prepareRecord(array $record)
    {
        return $record['Account'];
    }


    /**
     *
     */
    public function addMappingFieldsToSelect()
    {
        /** emulate lookup complete to load Update/Upsert mapping */
        $this->unit('lookup')->forceStatus(self::COMPLETE);
        $mapping = [];

        /** @var Input $upsertInput */
        $upsertInput = $this->unit('upsertInput');

        foreach ($this->entities() as $entity) {
            $entity->setForceUpdateOnly(true);

            if ($this->getMappingUnit()) {
                /** @var Collection $mapping */
                $mapping = $this->getMappingUnit()->mappers($entity);
            }
            $entity->setForceUpdateOnly(false);
            break;
        }

        /** stop lookup complete emulation */
        $this->unit('lookup')->restoreStatus();

        $definedColumns = $this->input->columns;
        // TODO : change it to the compareIgnoreFields as defined for \TNW\Salesforce\Synchronize\Unit\Upsert\Input
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

            if (!in_array(strtolower((string)'Account.' . $map->getSalesforceAttributeName()), $definedColumns)) {
                $this->input->columns[] = 'Account.' . $map->getSalesforceAttributeName();
                $definedColumns[] = strtolower((string)'Account.' . $map->getSalesforceAttributeName());
            }
        }
    }
}
