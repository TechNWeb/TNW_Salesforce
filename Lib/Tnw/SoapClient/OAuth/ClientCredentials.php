<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Lib\Tnw\SoapClient\OAuth;

/**
 * OAuth 2.0 Client Credentials of a Salesforce External Client App
 */
class ClientCredentials
{
    const TOKEN_PATH = '/services/oauth2/token';

    /** @var string */
    private $myDomainUrl;

    /** @var string */
    private $clientId;

    /** @var string */
    private $clientSecret;

    /**
     * @param string $myDomainUrl  Salesforce My Domain URL, e.g. https://acme.my.salesforce.com
     * @param string $clientId     Consumer Key of the External Client App
     * @param string $clientSecret Consumer Secret of the External Client App
     */
    public function __construct(string $myDomainUrl, string $clientId, string $clientSecret)
    {
        $myDomainUrl = rtrim(trim($myDomainUrl), '/');
        if ($myDomainUrl !== '' && strpos($myDomainUrl, '://') === false) {
            $myDomainUrl = 'https://' . $myDomainUrl;
        }

        $this->myDomainUrl = $myDomainUrl;
        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
    }

    /**
     * @return string
     */
    public function getMyDomainUrl(): string
    {
        return $this->myDomainUrl;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return string
     */
    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * @return string
     */
    public function getTokenUrl(): string
    {
        return $this->myDomainUrl . self::TOKEN_PATH;
    }
}
