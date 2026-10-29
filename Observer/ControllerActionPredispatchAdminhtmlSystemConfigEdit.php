<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Observer;

use Exception;
use Magento\Config\Controller\Adminhtml\System\Config\Edit;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\App\Action\Action;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use SoapFault;
use TNW\Salesforce\Client\Salesforce;
use TNW\Salesforce\Model\Config;

/**
 * Predispatch Observer
 */
class ControllerActionPredispatchAdminhtmlSystemConfigEdit implements ObserverInterface
{
    private const TNW_SECTION_PREFIX = 'tnwsforce_';
    private const TNW_GENERAL_SECTION = 'tnwsforce_general';
    private const LINK = 'https://technweb.atlassian.net/wiki/spaces/IWS/pages/50561027/REQUEST+LIMIT+EXCEEDED+TotalRequests+Limit+exceeded';

    /** @var UrlInterface */
    private $urlBuilder;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var Salesforce */
    private $salesforceClient;

    /** @var array */
    protected $allowSection = [
        'tnwsforce_customer',
        'tnwsforce_product',
        'tnwsforce_order',
        'tnwsforce_invoice',
        'tnwsforce_shipment',
        'tnwsforce_picklists',
    ];

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Config */
    private $salesforceConfig;

    /**
     * @param UrlInterface          $urlBuilder
     * @param ManagerInterface      $messageManager
     * @param Salesforce            $salesforceClient
     * @param Config                $salesforceConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        UrlInterface $urlBuilder,
        ManagerInterface $messageManager,
        Salesforce $salesforceClient,
        Config $salesforceConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->messageManager = $messageManager;
        $this->salesforceClient = $salesforceClient;
        $this->storeManager = $storeManager;
        $this->salesforceConfig = $salesforceConfig;
    }

    /**
     * @param Observer $observer
     * @throws FileSystemException|LocalizedException
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Http $request */
        $request = $observer->getEvent()->getData('request');
        if (!$this->isAllowedSection((string)$request->getParam('section'))) {
            return;
        }

        /** @var Edit $controllerAction */
        $controllerAction = $observer->getEvent()->getData('controller_action');

        if (!$this->salesforceClient->getClientStatus()) {
            $this->messageManager->addWarningMessage('Saleseforce Integration is disabled');
            $this->redirect($controllerAction);

            return;
        }

        $websiteId = $this->storeManager->getWebsite()->getId();
        $wsdl = $this->salesforceConfig->getSalesforceWsdl($websiteId);
        $userName = $this->salesforceConfig->getSalesforceUsername($websiteId);
        $password = $this->salesforceConfig->getSalesforcePassword($websiteId);
        $token = $this->salesforceConfig->getSalesforceToken($websiteId);
        $clientCredentials = $this->salesforceConfig->getOAuthClientCredentials($websiteId);

        try {
            $this->salesforceClient->checkConnection($wsdl, $userName, $password, $token, $clientCredentials);
        } catch (SoapFault $e) {
            switch (true) {
                case strcasecmp((string)$e->faultcode, 'sf:INVALID_OPERATION_WITH_EXPIRED_PASSWORD') === 0:
                    $this->messageManager->addErrorMessage(__('You Salesforce password is expired. Login to Salesforce, update password. Put new Password and token in our module configuration.'));
                    break;

                case strcasecmp((string)$e->faultcode, 'sf:INVALID_LOGIN') === 0:
                    $this->messageManager->addErrorMessage(__('Defined Salesforce login, password or token is incorrect. Please defined valid information.'));
                    break;

                case strcasecmp((string)$e->faultcode, 'WSDL') === 0:
                    $this->messageManager->addErrorMessage(__('The WSDL file is no available or corrupted. Upload new wsdl file.'));
                    break;

                case strcasecmp((string)$e->faultcode, 'sf:REQUEST_LIMIT_EXCEEDED') === 0:
                    $format = 'Salesforce Total Requests Limit exceeded. For more information click %s';
                    $link = sprintf(
                        '<a href="%s">here</a>',
                        self::LINK
                    );
                    $message = sprintf($format, $link);
                    $this->messageManager->addComplexErrorMessage('allowHtmlTagsMessage',
                        [
                            'message' => $message,
                            'allowed_tags' => ['a']
                        ],
                        'backend');
                    break;

                default:
                    $this->messageManager->addExceptionMessage($e);
                    break;
            }

            $this->redirect($controllerAction);

            return;
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e);
            $this->redirect($controllerAction);
        }
    }

    /**
     * @param Action $action
     */
    protected function redirect(Action $action)
    {
        $action->getActionFlag()->set('', Edit::FLAG_NO_DISPATCH, true);

        $response = $action->getResponse();
        $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit', ['section'=>'tnwsforce_general']);
        $response->setRedirect($url);
    }

    /**
     * @param string $sectionName
     *
     * @return bool
     */
    private function isAllowedSection(string $sectionName): bool
    {
        return (strpos($sectionName, self::TNW_SECTION_PREFIX) !== false)
            && $sectionName !== self::TNW_GENERAL_SECTION;
    }
}
