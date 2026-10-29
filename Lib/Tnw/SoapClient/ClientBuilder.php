<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Lib\Tnw\SoapClient;

use Magento\Framework\Exception\LocalizedException;
use TNW\Salesforce\Lib\Tnw\SoapClient\OAuth\ClientCredentials;
use Tnw\SoapClient\Soap\SoapClientFactory;
use Tnw\SoapClient\Plugin\LogPlugin;

/**
 * Class to extend Salesforce SOAP client builder
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class ClientBuilder extends \Tnw\SoapClient\ClientBuilder
{
    /**
     * The service endpoint is at the end of the WSDL, so only its tail is read first
     */
    const WSDL_TAIL_LENGTH = 65536;

    /** @var ClientCredentials|null */
    protected $clientCredentials;

    /**
     * Log in with the OAuth 2.0 Client Credentials flow, null keeps the SOAP login() call
     *
     * @param ClientCredentials|null $clientCredentials
     * @return $this
     */
    public function withClientCredentials(?ClientCredentials $clientCredentials)
    {
        $this->clientCredentials = $clientCredentials;

        return $this;
    }

    /**
     * Build the Salesforce SOAP client
     *
     * @return Client
     * @throws LocalizedException
     */
    public function build()
    {
        $soapClientFactory = new SoapClientFactory();
        $soapClient = $soapClientFactory->factory($this->wsdl, $this->soapOptions);

        $client = new Client($soapClient, $this->username, $this->password, $this->token);

        if ($this->clientCredentials) {
            $client->useClientCredentials($this->clientCredentials, $this->getSoapApiPath());
        }

        if ($this->log) {
            $logPlugin = new LogPlugin($this->log);
            $client->getEventDispatcher()->addSubscriber($logPlugin);
        }

        return $client;
    }

    /**
     * SOAP API path of the WSDL service endpoint, e.g. /services/Soap/c/64.0
     *
     * @return string
     * @throws LocalizedException
     */
    protected function getSoapApiPath()
    {
        $pattern = '|location=["\']https?://[^/"\']+(/services/Soap/[a-z]/\d+\.\d+)|i';
        $offset = max(0, (int)\filesize($this->wsdl) - self::WSDL_TAIL_LENGTH);

        if (preg_match($pattern, (string)\file_get_contents($this->wsdl, false, null, $offset), $matches)
            || ($offset > 0 && preg_match($pattern, (string)\file_get_contents($this->wsdl), $matches))
        ) {
            return $matches[1];
        }

        throw new LocalizedException(
            __('The API version could not be found in the WSDL file. Upload an Enterprise WSDL.')
        );
    }
}
