<?php
declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Log;

use Magento\Framework\DataObject;

/**
 * Log file data model.
 */
class File extends DataObject
{
    public const ID = 'id';
    public const NAME = 'name';
    public const TIME = 'time';
    public const SIZE = 'size';
    public const RELATIVE_PATH = 'relative_path';
    public const ABSOLUTE_PATH = 'absolute_path';

    /**
     * Get file id.
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->getData(self::ID);
    }

    /**
     * Set file id.
     *
     * @param string $id
     *
     * @return $this
     */
    public function setId(string $id): File
    {
        $this->setData(self::ID, $id);

        return $this;
    }

    /**
     * Get file name.
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * Set file name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName(string $name): File
    {
        $this->setData(self::NAME, $name);

        return $this;
    }

    /**
     * Get file updating time in UTC.
     *
     * @return string|null
     */
    public function getTime(): ?string
    {
        return $this->getData(self::TIME);
    }

    /**
     * Set file updating time in utc.
     *
     * @param string $time
     *
     * @return $this
     */
    public function setTime(string $time): File
    {
        $this->setData(self::TIME, $time);

        return $this;
    }

    /**
     * Get file size in bytes.
     *
     * @return int|null
     */
    public function getSize(): ?int
    {
        $size = $this->getData(self::TIME);

        return $size === null ? null : (int)$size;
    }

    /**
     * Set file size in bytes.
     *
     * @param int $size
     *
     * @return $this
     */
    public function setSize(int $size): File
    {
        $this->setData(self::SIZE, $size);

        return $this;
    }

    /**
     * Get log file relative path.
     *
     * @return string|null
     */
    public function getRelativePath(): ?string
    {
        return $this->getData(self::RELATIVE_PATH);
    }

    /**
     * Set log file relative path.
     *
     * @param string $path
     *
     * @return File
     */
    public function setRelativePath(string $path): File
    {
        $this->setData(self::RELATIVE_PATH, $path);

        return $this;
    }

    /**
     * Get log file absolute path.
     *
     * @return string|null
     */
    public function getAbsolutePath(): ?string
    {
        return $this->getData(self::ABSOLUTE_PATH);
    }

    /**
     * Set log file absolue path.
     *
     * @param string $path
     *
     * @return File
     */
    public function setAbsolutePath(string $path): File
    {
        $this->setData(self::ABSOLUTE_PATH, $path);

        return $this;
    }
}
