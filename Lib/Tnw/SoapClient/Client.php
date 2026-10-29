<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Lib\Tnw\SoapClient;

use Exception;
use Magento\Framework\App\Cache\State;
use Magento\Framework\App\Cache\Type\Collection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use SoapHeader;
use TNW\Salesforce\Lib\Tnw\SoapClient\OAuth\ClientCredentials;
use TNW\Salesforce\Lib\Tnw\SoapClient\OAuth\LoginResult as OAuthLoginResult;
use TNW\Salesforce\Lib\Tnw\SoapClient\OAuth\TokenRequest;
use Tnw\SoapClient\Event\RequestEvent;
use Tnw\SoapClient\Event\ResponseEvent;
use Tnw\SoapClient\Events;
use Tnw\SoapClient\Result\LoginResult;
use Tnw\SoapClient\Soap\SoapClient;
use Traversable;

/**
 * A class for extending the Salesforce SOAP API
 */
class Client extends \Tnw\SoapClient\Client
{
    const CACHE_TAG = 'AUTH_RESULT';

    const ERR_INVALID_SESSION = 'sf:INVALID_SESSION_ID';

    const ERR_REQUEST_LIMIT_EXCEEDED = 'sf:REQUEST_LIMIT_EXCEEDED';

    const LINK = 'https://technweb.atlassian.net/wiki/spaces/IWS/pages/50561027/REQUEST+LIMIT+EXCEEDED+TotalRequests+Limit+exceeded';

    const TIMEOUT_RESERVE = 180;

    /** @var Collection */
    protected $cacheCollection;

    /** @var State  */
    protected $cacheState;

    /** @var StoreManagerInterface  */
    protected $storeManager;

    protected $expirationTime;

    /** @var ClientCredentials|null */
    protected $clientCredentials;

    /** @var string */
    protected $soapApiPath = '';

    /** @var TokenRequest|null */
    protected $tokenRequest;

    /** @var bool */
    private $isRetryingInvalidSession = false;

    /**
     * Client constructors.
     * @param SoapClient $soapClient
     * @param string $username
     * @param string $password
     * @param string $token
     * @param Collection $cacheCollection
     * @param State $cacheState
     */
    public function __construct(
        SoapClient $soapClient,
        $username,
        $password,
        $token
    ) {

        parent::__construct($soapClient, $username, $password, $token);
    }

    /**
     * Log in with the OAuth 2.0 Client Credentials flow instead of the SOAP login() call
     *
     * @param ClientCredentials $clientCredentials
     * @param string $soapApiPath SOAP API path from the WSDL, e.g. /services/Soap/c/64.0
     * @param TokenRequest|null $tokenRequest
     * @return $this
     */
    public function useClientCredentials(
        ClientCredentials $clientCredentials,
        string $soapApiPath,
        ?TokenRequest $tokenRequest = null
    ) {
        $this->clientCredentials = $clientCredentials;
        $this->soapApiPath = $soapApiPath;
        $this->tokenRequest = $tokenRequest ?? new TokenRequest();

        return $this;
    }

    /**
     * @param string $username
     * @param string $password
     * @param string $token
     * @return LoginResult
     * @throws LocalizedException
     */
    public function doLogin($username, $password, $token)
    {
        if ($this->clientCredentials === null) {
            return parent::doLogin($username, $password, $token);
        }

        try {
            $tokenResponse = $this->tokenRequest->request($this->clientCredentials);
        } finally {
            $this->logTokenRequest();
        }

        $loginResult = new OAuthLoginResult($tokenResponse, $this->soapApiPath);
        $this->setLoginResult($loginResult);

        return $loginResult;
    }

    /**
     * Log OAuth token request without the secret and the access token
     */
    public function logTokenRequest()
    {
        $this->dispatch(
            Events::REQUEST,
            new RequestEvent(sprintf(
                "POST %s\ngrant_type=client_credentials&client_id=%s&client_secret=***",
                $this->clientCredentials->getTokenUrl(),
                $this->clientCredentials->getClientId()
            ))
        );

        $this->dispatch(
            Events::RESPONSE,
            new ResponseEvent(preg_replace(
                '/"access_token"\s*:\s*"[^"]*"/',
                '"access_token":"***"',
                $this->tokenRequest->getLastResponse()
            ))
        );
    }

    /**
     * @return int
     * @throws NoSuchEntityException
     */
    public function getWebsiteId()
    {
        return $this->storeManager->getStore()->getWebsiteId();
    }

    /**
     * @return LoginResult
     */
    public function getLoginResult()
    {
        return parent::getLoginResult();
    }

    /**
     * @param object $object
     * @param string $objectType
     * @return object
     */
    protected function createSObject($object, $objectType)
    {
        $sObject = parent::createSObject($object, $objectType);
        foreach (get_object_vars($sObject) as $field => $value) {
            if (!is_string($value)) {
                continue;
            }

            $sObject->$field = preg_replace('/[^\x9\xA\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
        }

        return $sObject;
    }

    /**
     * @return SoapClient
     */
    public function getSoapClient()
    {
        return $this->soapClient;
    }

    /**
     * @param array $ids
     * @return array|Traversable
     */
    public function callDelete(array $ids)
    {
        return $this->call(
            'delete',
            ['ids' => $ids]
        );
    }

    /**
     * Set Assignment rule to created/updated Lead
     *
     * @param AssignmentRuleHeader $header
     *
     * @return $this
     */
    public function setAssignmentRuleHeader(AssignmentRuleHeader $header)
    {
        $data = [
            'AssignmentRuleHeader' => [
                'assignmentRuleId' => $header->assignmentRuleId,
                'useDefaultRule' => $header->useDefaultRuleFlag
            ]
        ];

        $this->setSoapHeaders($data);

        return $this;
    }

    /**
     * @return bool
     */
    public function sessionExpired()
    {
        $currentTime = time();

        /** need skip this condition to allow fill the timeout at the first time */
        return !empty($this->expirationTime) && $currentTime > $this->expirationTime;
    }

    /**
     *
     */
    public function setExpirationTime()
    {
        $currentTime = time();
        $timeout = (int)$this->getUserInfo()->getSessionSecondsValid();
        $timeout -= self::TIMEOUT_RESERVE;

        $this->expirationTime = $currentTime + $timeout;
    }

    /**
     * @param LoginResult $loginResult
     */
    protected function setLoginResult(LoginResult $loginResult)
    {
        parent::setLoginResult($loginResult);
        $this->setExpirationTime();
    }

    /**
     *
     */
    public function resetSession()
    {
        $this->sessionHeader = null;
        $this->loginResult = null;
        $this->expirationTime = null;
        $this->soapClient->__setSoapHeaders(null);
    }

    /**
     * @param $method
     * @param array $params
     * @return mixed
     * @throws LocalizedException
     */
    protected function processLoginRequest($method, array $params = [])
    {
        try {
            $result = $this->soapClient->$method($params);

        } finally {
            $this->logLogin();
        }

        return $result;
    }

    /**
     *
     */
    public function logLogin()
    {
        $request = $this->soapClient->__getLastRequest();
        $response = $this->soapClient->__getLastResponse();

        $patterns = [
            "/<ns1:password>.*<\/ns1:password>/",
            "/<sessionId>.*<\/sessionId>/"
        ];

        $replace = [
            "<ns1:password>***</ns1:password>",
            "<sessionId>***</sessionId>"
        ];

        $request = preg_replace($patterns, $replace, $request);
        $response = preg_replace($patterns, $replace, $response);

        $this->dispatch(
            Events::REQUEST,
            new RequestEvent($request)
        );

        $this->dispatch(
            Events::RESPONSE,
            new ResponseEvent($response)
        );
    }

    /**
     * @param $method
     * @param array $params
     * @return mixed
     */

    protected function logcall($method, array $params = [])
    {
        if ($method != 'login') {
            return parent::logcall($method, $params);
        }
        return $this->processLoginRequest($method, $params);
    }

    /**
     * @param $method
     * @param array $params
     * @return array|Traversable
     * @throws \SoapFault
     */
    protected function call($method, array $params = array())
    {
        try {
            return parent::call($method, $params);
        } catch (\SoapFault $e) {
            if ($e->faultcode == self::ERR_REQUEST_LIMIT_EXCEEDED) {
                $format = 'Sorry but we can\'t load Salesforce data because of Total Request Limits exceeded.
                 For more information click %s';
                $link = sprintf(
                    '<a href="%s">here</a>',
                    self::LINK
                );
                $message = sprintf($format, $link);
                throw new Exception($message);
            }
            if ($e->faultcode == self::ERR_INVALID_SESSION && !$this->isRetryingInvalidSession) {
                // Log in again once. A new session that is still rejected (e.g. an OAuth token
                // without the "api" scope) must fail instead of logging in again and again.
                $this->resetSession();
                $this->isRetryingInvalidSession = true;
                try {
                    return parent::call($method, $params);
                } finally {
                    $this->isRetryingInvalidSession = false;
                }
            } else {
                throw $e;
            }
        }
    }
}
