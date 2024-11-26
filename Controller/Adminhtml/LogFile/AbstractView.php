<?php
declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Controller\Adminhtml\LogFile;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\View\Result\PageFactory;
use Throwable;
use TNW\Salesforce\Model\Log\File;
use TNW\Salesforce\Model\Log\FileFactory;
use TNW\Salesforce\Service\Tools\Log\LoadFileData;

/**
 * View log file base action.
 */
abstract class AbstractView extends Action
{
    /** @var PageFactory */
    private $resultPageFactory;

    /** @var LoadFileData */
    private $loadFileData;

    /** @var FileFactory */
    private $fileFactory;

    /** @var File[] */
    private $files;

    /**
     * @param Context      $context
     * @param PageFactory  $resultPageFactory
     * @param LoadFileData $loadFileData
     * @param FileFactory  $fileFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        LoadFileData $loadFileData,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->loadFileData = $loadFileData;
        $this->fileFactory = $fileFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        try {
            $this->initPageConfig($resultPage);
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $resultPage = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
        }

        return $resultPage;
    }

    /**
     * @param string $fileId
     *
     * @return File
     * @throws FileSystemException
     */
    protected function getFileModel(string $fileId): File
    {
        if (isset($this->files[$fileId])) {
            return $this->files[$fileId];
        }

        $model = $this->fileFactory->create();
        $this->loadFileData->execute($model, $fileId);
        if (!$model->getId()) {
            throw new FileSystemException(__('Requested log file %1 no longer exists.', $fileId));
        }

        $this->files[$fileId] = $model;

        return $this->files[$fileId];
    }

    /**
     * Setup page config.
     *
     * @param $resultPage
     */
    abstract protected function initPageConfig($resultPage): void;
}
