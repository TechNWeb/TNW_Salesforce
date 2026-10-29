<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Controller\Adminhtml\System\Log;

class Login extends \Magento\Backend\App\Action
{
    private const LINK = 'https://technweb.atlassian.net/wiki/spaces/IWS/pages/50561027/REQUEST+LIMIT+EXCEEDED+TotalRequests+Limit+exceeded';

    /**
     * @var \TNW\Salesforce\Client\Salesforce
     */
    private $salesforce;

    /**
     * @var \TNW\Salesforce\Model\Config
     */
    private $config;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \TNW\Salesforce\Client\Salesforce $salesforce,
        \TNW\Salesforce\Model\Config $config
    ) {
        parent::__construct($context);
        $this->salesforce = $salesforce;
        $this->config = $config;
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('TNW_Salesforce::tools_login');
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function execute()
    {
        $websiteId = $this->_request->getParam('website_id');

        // Client Credentials tokens can't open the Salesforce UI, the user signs in on the My Domain page
        $clientCredentials = $this->config->getOAuthClientCredentials($websiteId);
        if ($clientCredentials && $clientCredentials->getMyDomainUrl() !== '') {
            return $this->resultRedirectFactory->create()
                ->setUrl($clientCredentials->getMyDomainUrl());
        }

        try {
            $client = $this->salesforce->getClient($websiteId);
            if ($client) {
                $loginResult = $client->getLoginResult();

                return $this->resultRedirectFactory->create()
                    ->setUrl(sprintf(
                        'https://%s.salesforce.com/secur/frontdoor.jsp?sid=%s',
                        $loginResult->getServerInstance(),
                        $loginResult->getSessionId()
                    ));
            } else {
                $format = 'Salesforce Total Requests Limit exceeded. 
                Sorry but we can\'t Login to the Salesforce. For more information click %s';
                $link = sprintf(
                    '<a href="%s">here</a>',
                    self::LINK
                );
                $message = sprintf($format, $link);
                $exception = new \Exception($message);
                $this->messageManager->addExceptionMessage($exception);
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e);
        }

        return $this->resultRedirectFactory->create()
            ->setPath('tnw_salesforce/system_log/view');
    }
}
