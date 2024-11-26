<?php
declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Controller\Adminhtml\LogFile\Sync;

use Magento\Framework\Exception\FileSystemException;
use TNW\Salesforce\Controller\Adminhtml\LogFile\AbstractView;

/**
 * View log file action.
 */
class View extends AbstractView
{
    /**
     * @param $resultPage
     *
     * @throws FileSystemException
     */
    protected function initPageConfig($resultPage): void
    {
        $resultPage->setActiveMenu('TNW_Salesforce::tools_sync_log');
        $fileId = (string)$this->getRequest()->getParam('id');
        $file = $this->getFileModel($fileId);
        $resultPage->getConfig()->getTitle()->prepend((__('View Synchronization Log File: %1', $file->getName())));
    }
}
