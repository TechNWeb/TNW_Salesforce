<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;

class Filesystem extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * @var \TNW\Salesforce\Model\Config
     */
    private $salesforceConfig;

    /**
     * SForce constructor.
     * @param \Magento\Framework\Filesystem\DriverInterface $filesystem
     * @param \TNW\Salesforce\Model\Config $salesforceConfig
     * @param null $filePath
     */
    public function __construct(
        \Magento\Framework\Filesystem\DriverInterface $filesystem,
        \TNW\Salesforce\Model\Config $salesforceConfig,
        $filePath = null
    ) {
        $baseDay = $salesforceConfig->logBaseDay();
        $fileName = sprintf('/var/log/sforce/%d_%d_%d.log', date('Y'), $baseDay, floor((date('z') + 1) / $baseDay));
        $this->salesforceConfig = $salesforceConfig;

        /**
         * added for M2.1 compatibility
         */
        $this->fileName = $fileName;

        parent::__construct($filesystem, $filePath);
        $this->setFormatter(new LineFormatter("[%datetime%] [%extra.uid%] %level_name%: %message%\n"));
    }

    /**
     * @param array $record
     */
    public function write(array|\Monolog\LogRecord $record): void
    {
        if (!$this->salesforceConfig->getLogStatus()) {
            return;
        }

        parent::write($record);
    }
}
