<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use TNW\Salesforce\Client\Salesforce;
use TNW\Salesforce\Model\Config;

class InvalidConnection implements MessageInterface
{
    /**@var UrlInterface */
    protected $urlBuilder;

    /** @var Config */
    protected $config;

    /** @var Salesforce */
    protected $client;

    /** @var StoreManagerInterface */
    protected $storeManager;

    /**
     * InvalidConnection constructor.
     * @param UrlInterface $urlBuilder
     * @param Config $config
     * @param Salesforce $client
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        UrlInterface $urlBuilder,
        Config $config,
        Salesforce $client,
        StoreManagerInterface $storeManager
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->config = $config;
        $this->client = $client;
        $this->storeManager = $storeManager;
    }

    /**
     * Retrieve unique message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return hash('sha256', 'TNW_SALESFORCE_INVALID_CONNECTION');
    }

    /**
     * Check whether
     *
     * @return bool
     */
    public function isDisplayed()
    {
        $result = true;

        // get website id from url
        $websiteId = $this->storeManager->getWebsite()->getId();

        // read website specific configuration
        $wsdl = $this->config->getSalesforceWsdl($websiteId);
        $username = $this->config->getSalesforceUsername($websiteId);
        $password = $this->config->getSalesforcePassword($websiteId);
        $token = $this->config->getSalesforceToken($websiteId);
        $clientCredentials = $this->config->getOAuthClientCredentials($websiteId);

        try {
            if (!$this->config->getSalesforceStatus($websiteId)
                || (file_exists($wsdl)
                    && $this->client->checkConnection($wsdl, $username, $password, $token, $clientCredentials))
            ) {
                $result = false;
            }
        } catch (\Exception $e) {
            $result = true;
        }

        return $result;
    }

    /**
     * Retrieve message text
     *
     * @return string
     */
    public function getText()
    {
        $message = __('Salesforce connection cannot be established. ');
        $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/tnwsforce_general');
        $message .= __('<a href="%1">Check configuration</a> settings and re-test the connection.',
            $url);
        return $message;
    }

    /**
     * Retrieve message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return MessageInterface::SEVERITY_CRITICAL;
    }
}
