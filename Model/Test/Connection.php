<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Test;

/**
 * Class Connection
 * @package TNW\Salesforce\Model\Test
 */
class Connection extends  \TNW\Salesforce\Model\Test
{
    /**
     * @var string
     */
    protected $testLabel = 'Salesforce Connection';
    /**
     * @var null|\TNW\Salesforce\Model\Config
     */
    protected $config = null;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \TNW\Salesforce\Model\Config $config
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \TNW\Salesforce\Model\Config $config
    ) {
        parent::__construct($objectManager);
        $this->config = $config;
    }
    /**
     * @return Connection
     */
    public function execute()
    {
        $result = parent::STATUS_PASSED;
        /**
         * @var \TNW\Salesforce\Client\Salesforce $client
         */
        $client = $this->objectManager->get('\TNW\Salesforce\Client\Salesforce');
        $wsdl = $this->config->getSalesforceWsdl();
        $username = $this->config->getSalesforceUsername();
        $password = $this->config->getSalesforcePassword();
        $token = $this->config->getSalesforceToken();
        $clientCredentials = $this->config->getOAuthClientCredentials();

        try{
            $client->checkConnection($wsdl, $username, $password, $token, $clientCredentials);
        }catch (\Exception $e){
            $result = parent::STATUS_FAILED;
        }

        $this->setStatus($result);
        return $this;
    }
}
