<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Synchronize\Transport\Soap;

use Magento\Framework\Exception\LocalizedException;
use TNW\Salesforce\Model\Config;
use TNW\Salesforce\Lib\Tnw\SoapClient\ClientBuilder;

/**
 * Client Factory
 */
class ClientFactory
{
    /**
     * @var \TNW\Salesforce\Lib\Tnw\SoapClient\Client[]
     */
    private static $clients = [];

    /**
     * @var Config
     */
    protected $salesforceConfig;

    /**
     * @var Config\WebsiteDetector
     */
    protected $websiteDetector;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param Config $salesforceConfig
     * @param Config\WebsiteDetector $websiteDetector
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        Config $salesforceConfig,
        \TNW\Salesforce\Model\Config\WebsiteDetector $websiteDetector,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->salesforceConfig = $salesforceConfig;
        $this->websiteDetector = $websiteDetector;
        $this->logger = $logger;
    }

    /**
     * Client
     *
     * @param int|null $websiteId
     * @return \TNW\Salesforce\Lib\Tnw\SoapClient\Client
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function client($websiteId = null)
    {
        $websiteId = (int)$this->websiteDetector->detectCurrentWebsite($websiteId);

        /** @var \TNW\Salesforce\Lib\Tnw\SoapClient\Client $client */
        $client = !empty(self::$clients[$websiteId])? self::$clients[$websiteId]:null;
        if (!$client || $client->sessionExpired()) {
            self::$clients[$websiteId] = $this->create($websiteId);
        }

        return self::$clients[$websiteId];
    }

    /**
     * Create
     *
     * @param int|null $websiteId
     * @return \TNW\Salesforce\Lib\Tnw\SoapClient\Client
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function create($websiteId = null)
    {
        $websiteId = (int)$this->websiteDetector->detectCurrentWebsite($websiteId);

        $wsdl = $this->salesforceConfig->getSalesforceWsdl($websiteId);
        if (!\file_exists($wsdl)) {
            throw new LocalizedException(__('WSDL file is missing'));
        }

        $builder = new ClientBuilder(
            $wsdl,
            (string)$this->salesforceConfig->getSalesforceUsername($websiteId),
            (string)$this->salesforceConfig->getSalesforcePassword($websiteId),
            (string)$this->salesforceConfig->getSalesforceToken($websiteId)
        );
        $builder->withClientCredentials($this->salesforceConfig->getOAuthClientCredentials($websiteId));

        if ($this->salesforceConfig->getLogDebug()) {
            $builder->withLog($this->logger);
        }

        return $builder->build();
    }
}
