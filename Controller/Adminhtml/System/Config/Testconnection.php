<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
namespace TNW\Salesforce\Controller\Adminhtml\System\Config;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Config\Model\Config\Backend\File;
use Magento\Framework\App\Cache\Type\Collection;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use TNW\Salesforce\Model\File\UploaderFactory;
use TNW\Salesforce\Client\Salesforce;
use TNW\Salesforce\Lib\Tnw\SoapClient\OAuth\ClientCredentials;
use TNW\Salesforce\Model\Config;
use TNW\Salesforce\Model\Config\Source\Salesforce\AuthType;
use Zend_Cache;

/**
 * Class Testconnection
 * @package TNW\Salesforce\Controller\Adminhtml\System\Config
 */
class Testconnection extends Action
{
    const UPLOAD_DIR = 'wsdl/tmp';

    /** @var File */
    protected $file;

    /** @var JsonFactory */
    protected $resultJsonFactory;

    /** @var Filesystem\Directory\WriteInterface  */
    protected $_mediaDirectory;

    /** @var Collection */
    private $cacheCollection;

    /** @var UploaderFactory */
    private $_uploaderFactory;

    /** @var Salesforce */
    private $salesforceClient;

    /** @var Config */
    private $salesforceConfig;

    /**
     * @param Context         $context
     * @param File            $file
     * @param Filesystem      $filesystem
     * @param UploaderFactory $uploaderFactory
     * @param JsonFactory     $resultJsonFactory
     * @param Salesforce      $salesforceClient
     * @param Config          $salesforceConfig
     * @param Collection      $cacheCollection
     *
     * @throws FileSystemException
     */
    public function __construct(
        Context $context,
        File $file,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        JsonFactory $resultJsonFactory,
        Salesforce $salesforceClient,
        Config $salesforceConfig,
        Collection $cacheCollection
    ) {
        parent::__construct($context);
        $this->file = $file;
        $this->_mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->_uploaderFactory = $uploaderFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->salesforceClient = $salesforceClient;
        $this->salesforceConfig = $salesforceConfig;
        $this->cacheCollection = $cacheCollection;
    }

    /**
     * Perform Salesforce connection test
     * @throws FileSystemException
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        $file = $this->getRequest()->getFiles('file');
        if (!empty($file)) {

            $uploadDir = $this->_getUploadDir();
            try {
                $uploader = $this->_uploaderFactory->create(['fileId' => $file]);
                $uploader->setAllowedExtensions(['xml', 'wsdl']);
                $uploader->setAllowRenameFiles(false);
                $uploader->save($uploadDir);

                $response = ['success' => 'true', 'message' => 'file uploaded successfully'];
                return $resultJson->setData($response);
            } catch (Exception $e) {
                $response = ['error' => 'true', 'message' => $e->getMessage()];
                return $resultJson->setData($response);
            }
        }

        // get website id from url
        $websiteId = $this->getRequest()->getParam('website');

        // get wsld path
        $wsdl = $this->getRequest()->getParam('wsdl');

        if (strpos($wsdl, 'fakepath') === false) {
            // read website specific configuration
            $wsdl = $this->salesforceConfig->getSalesforceWsdl($websiteId);
            $username = $this->salesforceConfig->getSalesforceUsername($websiteId);
            $password = $this->salesforceConfig->getSalesforcePassword($websiteId);
            $token = $this->salesforceConfig->getSalesforceToken($websiteId);
            $clientCredentials = $this->salesforceConfig->getOAuthClientCredentials($websiteId);
        } else {
            // get config from current values
            $wsdl = $this->_getWsdlPath($wsdl);
            $username = $this->getRequest()->getParam('username');
            $password = $this->getRequest()->getParam('password');
            $token = $this->getRequest()->getParam('token');
            $clientCredentials = $this->getRequestClientCredentials($websiteId);
        }

        $this->cacheCollection->clean(
            Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG,
            [Salesforce::LAST_ERROR_CONNECTION_TAG]
        );

        try {
            $result = $this->salesforceClient->checkConnection(
                $wsdl,
                $username,
                $password,
                $token,
                $clientCredentials
            );
        } catch (Exception $e) {
            if (property_exists($e,'faultcode') && strcasecmp((string)$e->faultcode, 'sf:REQUEST_LIMIT_EXCEEDED') === 0) {
                $result = __('Salesforce Total Requests Limit exceeded. Please try in 24h.');
            } else {
                $result = $e->getMessage();
            }
        }

        $this->getResponse()->setBody($result);
    }

    /**
     * OAuth credentials from the unsaved form, the saved secret is used while the field is masked
     *
     * @param int|null $websiteId
     * @return ClientCredentials|null
     */
    private function getRequestClientCredentials($websiteId)
    {
        if ($this->getRequest()->getParam('auth_type') !== AuthType::CLIENT_CREDENTIALS) {
            return null;
        }

        $clientSecret = (string)$this->getRequest()->getParam('oauth_client_secret');
        if ($clientSecret === '' || preg_match('/^\*+$/', $clientSecret)) {
            $clientSecret = $this->salesforceConfig->getSalesforceOAuthClientSecret($websiteId);
        }

        return new ClientCredentials(
            (string)$this->getRequest()->getParam('oauth_domain'),
            (string)$this->getRequest()->getParam('oauth_client_id'),
            $clientSecret
        );
    }

    /**
     * Return path to directory for upload file
     *
     * @return string
     * @throw \Magento\Framework\Exception\LocalizedException
     */
    protected function _getUploadDir()
    {
        return $this->_mediaDirectory->getAbsolutePath(self::UPLOAD_DIR);
    }

    /**
     * @param $wsdl
     * @return string
     */
    protected function _getWsdlPath($wsdl)
    {
        $wsdl = basename($wsdl);

        $fileName = substr(strrchr($wsdl, '\\'), 1);
        if (empty($fileName)) {
            $fileName = $wsdl;
        }

        return $this->_mediaDirectory->getAbsolutePath(self::UPLOAD_DIR) . '/' . $fileName;
    }
}
