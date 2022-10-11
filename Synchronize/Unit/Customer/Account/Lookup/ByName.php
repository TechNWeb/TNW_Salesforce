<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Synchronize\Unit\Customer\Account\Lookup;

use TNW\Salesforce\Service\Synchronize\Unit\Customer\Account\Lookup\ByName\GetMapperName;
use TNW\Salesforce\Synchronize;
use TNW\Salesforce\Model;
use TNW\Salesforce\Synchronize\Group;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\InputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\Query\OutputFactory;
use TNW\Salesforce\Synchronize\Transport\Calls\QueryInterface;
use TNW\Salesforce\Synchronize\Unit\IdentificationInterface;
use TNW\Salesforce\Synchronize\Units;

/**
 * Account Lookup By Name
 *
 * @method \Magento\Customer\Model\Customer[] entities()
 */
class ByName extends Synchronize\Unit\LookupAbstract
{
    /** @var GetMapperName */
    private $getMapperName;

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
            $this->input[$entity]['AND']['Name']['IN'][] = $this->valueCompany($entity);
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
        return $mapperName instanceof Model\Mapper && null !== $mapperName->getId()
            ? $this->unit('mapping')->value($entity, $mapperName)
            : Synchronize\Unit\Customer\Account\Mapping::companyByCustomer($entity);
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
        if (!empty($searchIndex['name'])) {
            $recordsIds[10] = array_keys($searchIndex['name'], strtolower((string)$this->valueCompany($entity)));
        }

        return $recordsIds;
    }
}
