<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Lib\Tnw\SoapClient\OAuth;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;

/**
 * Requests an access token with the OAuth 2.0 Client Credentials flow
 */
class TokenRequest
{
    const TIMEOUT = 60;

    /** @var Curl|null */
    private $curl;

    /** @var string */
    private $lastResponse = '';

    /**
     * @param Curl|null $curl
     */
    public function __construct(?Curl $curl = null)
    {
        $this->curl = $curl;
    }

    /**
     * Request access token
     *
     * @param ClientCredentials $credentials
     * @return array Token response: access_token, instance_url, id, ...
     * @throws LocalizedException
     */
    public function request(ClientCredentials $credentials): array
    {
        $this->lastResponse = '';

        if ($credentials->getMyDomainUrl() === ''
            || $credentials->getClientId() === ''
            || $credentials->getClientSecret() === ''
        ) {
            throw new LocalizedException(
                __('Salesforce My Domain URL, Consumer Key and Consumer Secret are required.')
            );
        }

        $curl = $this->curl ?? new Curl();
        $curl->setTimeout(self::TIMEOUT);
        $curl->addHeader('Accept', 'application/json');

        try {
            $curl->post($credentials->getTokenUrl(), [
                'grant_type' => 'client_credentials',
                'client_id' => $credentials->getClientId(),
                'client_secret' => $credentials->getClientSecret(),
            ]);
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Salesforce could not be reached at %1: %2', $credentials->getTokenUrl(), $e->getMessage()),
                $e
            );
        }

        $this->lastResponse = (string)$curl->getBody();
        $response = json_decode($this->lastResponse, true);
        if (!is_array($response)) {
            $response = [];
        }

        if ((int)$curl->getStatus() !== 200
            || empty($response['access_token'])
            || empty($response['instance_url'])
        ) {
            throw new LocalizedException(__(
                'Salesforce OAuth login failed: %1 %2',
                $response['error'] ?? sprintf('HTTP %d', $curl->getStatus()),
                $response['error_description'] ?? ''
            ));
        }

        return $response;
    }

    /**
     * Raw body of the last token response
     *
     * @return string
     */
    public function getLastResponse(): string
    {
        return $this->lastResponse;
    }
}
