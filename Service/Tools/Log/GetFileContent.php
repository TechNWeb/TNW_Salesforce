<?php
declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Service\Tools\Log;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Io\File;
use SplFileObject;
use TNW\Salesforce\Model\Config;

/**
 * Get file content service.
 */
class GetFileContent
{
    /** @var File */
    private $ioFile;

    /** @var Config */
    private $config;

    /**
     * @param File   $ioFile
     * @param Config $config
     */
    public function __construct(File $ioFile, Config $config)
    {
        $this->ioFile = $ioFile;
        $this->config = $config;
    }

    /**
     * Get file content.
     *
     * @param string   $filePath
     * @param int|null $pageSize
     * @param int      $page
     *
     * @return string
     * @throws FileSystemException
     */
    public function execute(string $filePath, int $page = 1, int $pageSize = null): string
    {
        if (!$this->ioFile->fileExists($filePath)) {
            throw new FileSystemException(__('Log file %1 no longer exist', $filePath));
        }

        $content = '';
        $pageSize = $pageSize ?? $this->config->getLinesCount();
        $firstLine = ($page - 1) * $pageSize;
        $endLine = $page * $pageSize;
        $file = new SplFileObject($filePath);
        $file->seek($firstLine);
        while ($file->key() < $endLine) {
            $content .= $file->current();

            $file->next();
            if (!$file->valid()) {
                break;
            }
        }

        return $content;
    }
}
