<?php
declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Controller\Adminhtml\LogFile\File;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory as FileResponseFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\FileSystemException;
use Throwable;
use TNW\Salesforce\Model\Log\File;
use TNW\Salesforce\Model\Log\FileFactory;
use TNW\Salesforce\Service\Tools\Log\LoadFileData;

/**
 * Log file download action.
 */
class Download extends Action implements HttpGetActionInterface
{
    /** @var LoadFileData */
    private $loadFileData;

    /** @var FileResponseFactory */
    private $fileResponseFactory;

    /** @var FileFactory */
    private $fileFactory;

    /**
     * @param Context             $context
     * @param FileResponseFactory $fileResponseFactory
     * @param LoadFileData        $loadFileData
     * @param FileFactory         $fileFactory
     */
    public function __construct(
        Context $context,
        FileResponseFactory $fileResponseFactory,
        LoadFileData $loadFileData,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->loadFileData = $loadFileData;
        $this->fileResponseFactory = $fileResponseFactory;
        $this->fileFactory = $fileFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $fileId = (string)$this->getRequest()->getParam('id');

        try {
            $model = $this->getFileModel($fileId);
            $content = [
                'type' => 'filename',
                'value' => $model->getRelativePath(),
            ];
            $response = $this->fileResponseFactory->create($model->getName(), $content, DirectoryList::LOG);
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $response = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
        }

        return $response;
    }

    /**
     * @param string $fileId
     *
     * @return File
     * @throws FileSystemException
     */
    private function getFileModel(string $fileId): File
    {
        $model = $this->fileFactory->create();
        $this->loadFileData->execute($model, $fileId);
        if (!$model->getId()) {
            throw new FileSystemException(__('Requested log file %1 no longer exists.', $fileId));
        }

        return $model;
    }
}
