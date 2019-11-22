<?php
namespace TNW\Salesforce\Synchronize\Unit\Customer\Account;

use Magento\Customer\Model\Address;
use TNW\Salesforce\Synchronize;
use TNW\Salesforce\Model;

/**
 * Customer Account Mapping
 */
class Mapping extends Synchronize\Unit\Mapping
{
    /**
     * @var \TNW\Salesforce\Model\Customer\Config
     */
    private $customerConfig;

    /**
     * Mapping constructor.
     *
     * @param string $name
     * @param string $load
     * @param string $lookup
     * @param string $objectType
     * @param Synchronize\Units $units
     * @param Synchronize\Group $group
     * @param Synchronize\Unit\IdentificationInterface $identification
     * @param Model\ResourceModel\Mapper\CollectionFactory $mapperCollectionFactory
     * @param Model\Customer\Config $customerConfig
     * @param array $dependents
     */
    public function __construct(
        $name,
        $load,
        $lookup,
        $objectType,
        Synchronize\Units $units,
        Synchronize\Group $group,
        Synchronize\Unit\IdentificationInterface $identification,
        Model\ResourceModel\Mapper\CollectionFactory $mapperCollectionFactory,
        Model\Customer\Config $customerConfig,
        array $dependents = []
    ) {
        parent::__construct(
            $name,
            $load,
            $lookup,
            $objectType,
            $units,
            $group,
            $identification,
            $mapperCollectionFactory,
            $dependents
        );

        $this->customerConfig = $customerConfig;
    }

    /**
     * Object By Entity Type
     *
     * @param \Magento\Customer\Model\Customer $entity
     * @param string $magentoEntityType
     * @return mixed
     */
    protected function objectByEntityType($entity, $magentoEntityType)
    {
        switch ($magentoEntityType) {
            case 'customer':
                return $entity;

            case 'customer_address/shipping':
                return $entity->getDefaultShippingAddress();

            case 'customer_address/billing':
                return $entity->getDefaultBillingAddress();

            default:
                return parent::objectByEntityType($entity, $magentoEntityType);
        }
    }

    /**
     * Prepare Value
     *
     * @param \Magento\Framework\Model\AbstractModel $entity
     * @param string $attributeCode
     * @return mixed
     * @throws \RuntimeException
     */
    public function prepareValue($entity, $attributeCode)
    {
        if ($entity instanceof \Magento\Customer\Model\Customer && strcasecmp($attributeCode, 'sforce_id') === 0) {
            return $this->units()->get('lookup')->get('%s/record/Id', $entity);
        }

        return parent::prepareValue($entity, $attributeCode);
    }

    /**
     * Default Value
     *
     * @param \Magento\Customer\Model\Customer $entity
     * @param \TNW\Salesforce\Model\Mapper $mapper
     * @return mixed
     */
    protected function defaultValue($entity, $mapper)
    {
        $default = parent::defaultValue($entity, $mapper);

        if (empty($default) && strcasecmp($mapper->getSalesforceAttributeName(), 'Name') === 0) {
            return self::generateCompanyByCustomer($entity);
        }

        if (strcasecmp($mapper->getSalesforceAttributeName(), 'OwnerId') === 0) {
            return $this->customerConfig->defaultOwner($entity->getData('config_website'));
        }

        return $default;
    }

    /**
     * Company By Customer
     *
     * @param \Magento\Customer\Model\Customer $entity
     * @return string
     */
    public static function companyByCustomer($entity)
    {
        $company = self::getCompanyByCustomer($entity);
        if (empty($company)) {
            $company = self::generateCompanyByCustomer($entity);
        }

        return $company;
    }

    /**
     * Get Company By Customer
     *
     * @param \Magento\Customer\Model\Customer $entity
     * @return string
     */
    public static function getCompanyByCustomer($entity)
    {
        $companyName = '';

        $address = $entity->getDefaultBillingAddress();
        if ($address instanceof Address) {
            $companyName = $address->getData('company');
        }

        return $companyName;
    }

    /**
     * Generate Company By Customer
     *
     * @param \Magento\Customer\Model\Customer $entity
     * @return string
     */
    public static function generateCompanyByCustomer($entity)
    {
        return trim(sprintf('%s %s', trim($entity->getFirstname()), trim($entity->getLastname())));
    }
}
