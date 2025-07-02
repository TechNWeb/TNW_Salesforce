<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Logger\Processor;

/**
 * UidProcessor
 */
class UidProcessor
{
    /**
     * @var string
     */
    private $uid;

    /**
     * @var int
     */
    private $length;

    /**
     * UidProcessor constructor.
     * @param int $length
     */
    public function __construct($length = 7)
    {
        $this->length = $length;
        $this->uid = static::generate($length);
    }

    /**
     * Invoke
     *
     * @param array $record
     * @return array
     */
    public function __invoke($record)
    {
        $record['extra']['uid'] = $this->uid;

        return $record;
    }

    /**
     * Uid
     *
     * @return string
     */
    public function uid()
    {
        return $this->uid;
    }

    /**
     * Refresh
     */
    public function refresh()
    {
        $this->uid = static::generate($this->length);
    }

    /**
     * Generate
     *
     * @param int $length
     * @return bool|string
     */
    public static function generate($length)
    {
        if (!is_int($length) || $length > 32 || $length < 1) {
            throw new \InvalidArgumentException('The uid length must be an integer between 1 and 32');
        }

        return substr(hash('md5', uniqid('', true)), 0, $length);
    }
}
