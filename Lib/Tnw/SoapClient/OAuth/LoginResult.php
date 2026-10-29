<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Lib\Tnw\SoapClient\OAuth;

/**
 * SOAP login result built from an OAuth token response.
 *
 * The access token is used as the SOAP session id, so everything that reads the login result
 * (SOAP endpoint, Bulk API session, instance URL) keeps working without the SOAP login() call.
 */
class LoginResult extends \Tnw\SoapClient\Result\LoginResult
{
    /**
     * @param array $tokenResponse Token response with access_token, instance_url and id
     * @param string $soapApiPath  SOAP API path from the WSDL, e.g. /services/Soap/c/64.0
     */
    public function __construct(array $tokenResponse, string $soapApiPath)
    {
        $instanceUrl = rtrim((string)$tokenResponse['instance_url'], '/');

        // Identity URL: https://login.salesforce.com/id/<organization id>/<user id>
        $identity = array_values(array_filter(explode('/', (string)parse_url(
            (string)($tokenResponse['id'] ?? ''),
            PHP_URL_PATH
        ))));
        $organizationId = $identity[1] ?? '';

        $this->sessionId = (string)$tokenResponse['access_token'];
        $this->serverUrl = $instanceUrl . $soapApiPath . ($organizationId !== '' ? '/' . $organizationId : '');
        $this->metadataServerUrl = $instanceUrl
            . preg_replace('|/Soap/[a-z]/|i', '/Soap/m/', $soapApiPath)
            . ($organizationId !== '' ? '/' . $organizationId : '');
        $this->userId = $identity[2] ?? null;
        $this->sandbox = strpos((string)parse_url($instanceUrl, PHP_URL_HOST), '.sandbox.') !== false;
        $this->passwordExpired = false;
    }
}
