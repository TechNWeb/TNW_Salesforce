<?php
declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Service\Tools\Log;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use TNW\Salesforce\Model\Log\File as Log;

/**
 * Load log file data.
 */
class LoadFileData
{
    /** @var IoFile */
    private $ioFile;

    /** @var File */
    private $file;

    /** @var TimezoneInterface */
    private $localDate;

    /** @var DateTime */
    private $dateTime;

    /** @var DirectoryList */
    private $directoryList;

    /**
     * @param IoFile            $ioFile
     * @param File              $file
     * @param TimezoneInterface $localDate
     * @param DateTime          $dateTime
     * @param DirectoryList     $directoryList
     */
    public function __construct(
        IoFile $ioFile,
        File $file,
        TimezoneInterface $localDate,
        DateTime $dateTime,
        DirectoryList $directoryList
    ) {
        $this->ioFile = $ioFile;
        $this->file = $file;
        $this->localDate = $localDate;
        $this->dateTime = $dateTime;
        $this->directoryList = $directoryList;
    }

    /**
     * Load log file data to model by filepath.
     *
     * @param Log    $logModel
     * @param string $id Relative path to log file.
     *
     * @throws FileSystemException
     */
    public function execute(Log $logModel, string $id): void
    {
        $path = $this->getAbsolutePath($id);
        if (!$this->ioFile->fileExists($path)) {
            return;
        }

        $fileInfo = $this->ioFile->getPathInfo($path);
        $fileName = $fileInfo['basename'] ?? '';
        $basePath = $this->getBasePath($path);
        $statistics = $this->file->stat($path);
        $createdAt = $statistics['ctime'] ?? '';
        $dateTime = $this->localDate->date($createdAt);
        $gmtDateTime = $this->dateTime->gmtDate('Y-m-d H:i:s', $dateTime);

        $logModel->setId($basePath)
            ->setName($fileName)
            ->setTime($gmtDateTime)
            ->setSize($statistics['size'] ?? '')
            ->setRelativePath($basePath)
            ->setAbsolutePath($path);
    }

    /**
     * @throws FileSystemException
     */
    private function getBasePath(string $absoluteFilePath): string
    {
        $absoluteDirectoryPath = $this->directoryList->getPath(DirectoryList::LOG);
        $basePath = str_replace($absoluteDirectoryPath, '', $absoluteFilePath);

        return trim($basePath, DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $relativePath
     *
     * @return string
     * @throws FileSystemException
     */
    private function getAbsolutePath(string $relativePath): string
    {
        $absoluteDirectoryPath = $this->directoryList->getPath(DirectoryList::LOG);
        $absoluteDirectoryPath = rtrim($absoluteDirectoryPath, DIRECTORY_SEPARATOR);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

        return $absoluteDirectoryPath . DIRECTORY_SEPARATOR . $relativePath;
    }
}
