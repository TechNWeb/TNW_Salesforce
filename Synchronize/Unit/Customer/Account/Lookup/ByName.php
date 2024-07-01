<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Unit\Customer\Account\Lookup;

use TNW\Salesforce\Model;
use TNW\Salesforce\Service\Synchronize\Unit\Customer\Account\Lookup\ByName\GetMapperName;
use TNW\Salesforce\Synchronize;
use TNW\Salesforce\Synchronize\Group;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\InputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\OutputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\QueryInterface;
use TNW\Salesforce\Synchronize\Unit\IdentificationInterface;
use TNW\Salesforce\Synchronize\Units;
use TNW\Salesforce\Utils\Company;
/**
 * Account Lookup By Name
 *
 * @method \Magento\Customer\Model\Customer[] entities()
 */
class ByName extends Synchronize\Unit\LookupAbstract
{
    /** @var GetMapperName */
    private $getMapperName;

    const NAME_PRIORITY = 10;

    /**
     * ByName constructor.
     *
     * @param string                  $name
     * @param string                  $load
     * @param Units                   $units
     * @param Group                   $group
     * @param IdentificationInterface $identification
     * @param InputFactory            $inputFactory
     * @param OutputFactory           $outputFactory
     * @param QueryInterface          $process
     * @param GetMapperName           $getMapperName
     * @param array                   $dependents
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
        GetMapperName $getMapperName,
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
        $this->getMapperName = $getMapperName;
    }

    /**
     * Process Input
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     * @throws \Zend_Db_Statement_Exception
     */
    public function processInput()
    {
        $this->input->columns['id'] = 'Id';
        $this->input->columns['owner'] = 'OwnerId';
        $this->input->columns['name'] = 'Name';

        foreach ($this->entities() as $entity) {
            $cacheObject = $this->getCacheObject();
            $company = $this->valueCompany($entity);
            !empty($company) && $this->input[$cacheObject]['AND']['']['OR']['Name']['IN'][] = $company;
            !empty($entity->getData('sforce_account_id')) && $this->input[$cacheObject]['AND']['']['OR']['Id']['IN'][] = $entity->getData('sforce_account_id');
        }

        $this->input->from = 'Account';
    }

    /**
     * Value Company
     *
     * @param \Magento\Customer\Model\Customer $entity
     * @return mixed|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     * @throws \Zend_Db_Statement_Exception
     */
    private function valueCompany($entity)
    {
        $mapperName = $this->mapperName($this->load()->get('websiteIds/%s', $entity));
        $companyName = $mapperName instanceof Model\Mapper && null !== $mapperName->getId()
            ? $this->unit('mapping')->value($entity, $mapperName)
            : Synchronize\Unit\Customer\Account\Mapping::companyByCustomer($entity);

        // avoid lookup for Account create as "FirstName Lastname",
        // in this case we should create a new Account to avoid merge individual Customers with the same names
        return $companyName != Company::generateCompanyByCustomer($entity) ? $companyName : '';
    }

    /**
     * @param $websiteId
     *
     * @return Model\Mapper|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     * @throws \Zend_Db_Statement_Exception
     */
    public function mapperName($websiteId)
    {
        return $this->getMapperName->execute((int)$websiteId);
    }

    /**
     * Collect Index
     *
     * @return array
     */
    public function collectIndex()
    {
        $searchIndex = [];
        foreach ($this->output as $key => $record) {
            if (!empty($record['Name'])) {
                $searchIndex['name'][$key] = strtolower((string)$record['Name']);
            }

            if (!empty($record['Id'])) {
                $searchIndex['salesforceId'][$key] = $record['Id'];
            }
        }

        return $searchIndex;
    }

    /**
     * Search Priority Order
     *
     * @param array $searchIndex
     * @param \Magento\Customer\Model\Customer $entity
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     * @throws \Zend_Db_Statement_Exception
     */
    public function searchPriorityOrder(array $searchIndex, $entity)
    {
        $recordsIds = [];

        if (!empty($searchIndex['salesforceId'])) {

            $recordsIds[5] = array_keys(
                $searchIndex['salesforceId'],
                $entity->getData('sforce_account_id')
            );
        }

        if (!empty($searchIndex['name'])) {
            $recordsIds[self::NAME_PRIORITY] = array_keys($searchIndex['name'], strtolower((string)$this->valueCompany($entity)));
        }

        return $recordsIds;
    }
}
