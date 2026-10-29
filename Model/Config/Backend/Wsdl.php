<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Config\Backend;

use Exception;
use Magento\Config\Model\Config\Backend\File\RequestData\RequestDataInterface;
use Magento\Config\Model\ResourceModel\Config;
use Magento\Framework\App\Cache\State;
use Magento\Framework\App\Cache\Type\Collection;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use TNW\Salesforce\Model\File\Uploader;
use TNW\Salesforce\Model\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Xml\Parser;
use TNW\Salesforce\Model\Config\WebsiteDetector;

class Wsdl extends Value
{
    const CACHE_TAG = 'tnw_salesforce_client';

    const USE_BULK_API_VERSION = 'tnwsforce_general/synchronization/use_bulk_api_version';

    const USE_BULK_API_VERSION_CACHE_IDENTIFIER = 'use_bulk_api_version';

    const API_URL = 'https://login.salesforce.com/services/Soap/c/';

    /** @var Parser */
    protected $xmlParser;

    /** @var Config */
    protected $resourceConfig;

    /** @var WebsiteDetector  */
    protected $websiteDetector;

    /** @var Collection  */
    protected $cacheCollection;

    /** @var  State */
    protected $cacheState;

    /** @var  array */
    protected $handCache = [];

    /**
     * @var RequestDataInterface
     */
    protected $_requestData;

    /**
     * Upload max file size in kilobytes
     *
     * @var int
     */
    protected $_maxFileSize = 0;

    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * @var WriteInterface
     */
    protected $_mediaDirectory;

    /**
     * @var UploaderFactory
     */
    protected $_uploaderFactory;

    /**
     * The tail part of directory path for uploading
     *
     */
    const UPLOAD_DIR = 'wsdl';
    /**
     * @var SerializerInterface|null
     */
    private $serializer;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        UploaderFactory $uploaderFactory,
        RequestDataInterface $requestData,
        Filesystem $filesystem,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        Parser $xmlParser,
        Config $resourceConfig,
        WebsiteDetector $websiteDetector,
        Collection $cacheCollection,
        State $cacheState,
        ObjectManagerInterface $objectManager,
        array $data = [],
        SerializerInterface $serializer = null
    ) {
        $this->_uploaderFactory = $uploaderFactory;
        $this->_requestData = $requestData;
        $this->_filesystem = $filesystem;
        $this->_mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->_mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->xmlParser = $xmlParser;
        $this->resourceConfig = $resourceConfig;
        $this->websiteDetector = $websiteDetector;
        $this->cacheCollection = $cacheCollection;
        $this->cacheState = $cacheState;
        $this->serializer = $serializer ?? $objectManager->get(SerializerInterface::class);
    }

    /**
     * Save uploaded file before saving config value
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $file = $this->getFileData();
        if (!empty($file)) {
            $uploadDir = $this->_getUploadDir();
            try {
                /** @var Uploader $uploader */
                $uploader = $this->_uploaderFactory->create(['fileId' => $file]);
                $uploader->setAllowedExtensions($this->_getAllowedExtensions());
                $uploader->setAllowRenameFiles(false);
                $result = $uploader->save($uploadDir);

                $loadedxmlfile = $result['path'] . '/' . $result['file'];

                $parsedXMLData = $this->xmlParser->load($loadedxmlfile)->xmlToArray();

                if (isset($parsedXMLData['definitions']['_value']['service'])) {
                    $serviceNode = $parsedXMLData['definitions']['_value']['service'];
                    $apiVersion = $this->getAPIVersion($serviceNode);

                    if ($apiVersion) {
                        $this->resourceConfig->saveConfig(
                            'tnwsforce_general/synchronization/use_bulk_api_version',
                            $apiVersion,
                            'default',
                            0
                        );
                        $websiteId = $this->websiteDetector->detectCurrentWebsite();
                        $this->saveCache($apiVersion, self::USE_BULK_API_VERSION_CACHE_IDENTIFIER, $websiteId);
                    }
                }
            } catch (Exception $e) {
                throw new LocalizedException(__('%1', $e->getMessage()));
            }

            $filename = $this->_mediaDirectory->getRelativePath("{$result['path']}/{$result['file']}");

            $this->setValue("{var}/$filename");
        } else {
            $this->setValue($value[0]);
        }

        return $this;
    }

    /**
     * Return path to directory for upload file
     *
     * @return string
     * @throw \Magento\Framework\Exception\LocalizedException
     */
    protected function _getUploadDir()
    {
        return $this->_mediaDirectory->getAbsolutePath($this->_appendScopeInfo(self::UPLOAD_DIR));
    }

    /**
     * Makes a decision about whether to add info about the scope.
     *
     * @return boolean
     */
    protected function _addWhetherScopeInfo()
    {
        return true;
    }

    /**
     * @param $serviceNode
     * @return mixed
     */
    public function getAPIVersion($serviceNode)
    {
        // https://login.salesforce.com/services/Soap/c/46.0/0DF1p000000HDYU
        $location = (string)$this->arrayFindValueByKey($serviceNode, 'location');
        $locationArray = explode('/', $location);

        $apiversion = $locationArray[6];

        return $apiversion;
    }

    /**
     * @param $array
     * @param $search
     * @param array $keys
     * @return string|array
     */
    public function arrayFindValueByKey($array, $search, $keys = [])
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sub = $this->arrayFindValueByKey($value, $search, array_merge($keys, [$key]));
                if (is_scalar($sub) || count($sub)) {
                    return $sub;
                }
            } elseif ($key === $search) {
                return $value;
            }
        }

        return [];
    }

    /**
     * Save cache
     *
     * @param String $value
     * @param String $identifier
     * @param int|null $websiteId
     *
     * @throws LocalizedException
     */
    protected function saveCache($value, $identifier)
    {
        $websiteId = $this->websiteDetector->detectCurrentWebsite();

        if ($this->cacheState->isEnabled(Collection::TYPE_IDENTIFIER)) {

            /** @var mixed $serialized */
            $serialized = $this->serializer->serialize($value);

            $this->cacheCollection->save(
                $serialized,
                self::CACHE_TAG . $identifier
            );
        } else {
            $this->handCache[$identifier] = $value;
        }
    }

    /**
     * Receiving uploaded file data
     *
     * @return array
     * @since 100.1.0
     */
    protected function getFileData()
    {
        $file = [];
        $value = $this->getValue();
        $tmpName = $this->_requestData->getTmpName($this->getPath());
        if ($tmpName) {
            $file['tmp_name'] = $tmpName;
            $file['name'] = $this->_requestData->getName($this->getPath());
        } elseif (!empty($value['tmp_name'])) {
            $file['tmp_name'] = $value['tmp_name'];
            $file['name'] = isset($value['value']) ? $value['value'] : $value['name'];
        }

        return $file;
    }

    /**
     * Retrieve upload directory path
     *
     * @param string $uploadDir
     * @return string
     * @since 100.1.0
     */
    protected function getUploadDirPath($uploadDir)
    {
        return $this->_mediaDirectory->getAbsolutePath($uploadDir);
    }

    /**
     * Prepend path with scope info
     *
     * E.g. 'stores/2/path' , 'websites/3/path', 'default/path'
     *
     * @param string $path
     * @return string
     */
    protected function _prependScopeInfo($path)
    {
        $scopeInfo = $this->getScope();
        if (ScopeConfigInterface::SCOPE_TYPE_DEFAULT != $this->getScope()) {
            $scopeInfo .= '/' . $this->getScopeId();
        }
        return $scopeInfo . '/' . $path;
    }

    /**
     * Add scope info to path
     *
     * E.g. 'path/stores/2' , 'path/websites/3', 'path/default'
     *
     * @param string $path
     * @return string
     */
    protected function _appendScopeInfo($path)
    {
        $path .= '/' . $this->getScope();
        if (ScopeConfigInterface::SCOPE_TYPE_DEFAULT != $this->getScope()) {
            $path .= '/' . $this->getScopeId();
        }
        return $path;
    }

    /**
     * Getter for allowed extensions of uploaded files
     *
     * @return array
     */
    protected function _getAllowedExtensions()
    {
        return [];
    }
}
