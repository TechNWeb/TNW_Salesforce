<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Model\Logger\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class Database extends AbstractProcessingHandler
{
    const MESSAGE_LIMIT_SIZE = 65000;

    /**
     * @var \TNW\Salesforce\Model\Log
     */
    protected $databaseLogger;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $systemLogger;

    /**
     * @var \TNW\Salesforce\Model\Config
     */
    private $salesforceConfig;

    /**
     * Database constructor.
     * @param \TNW\Salesforce\Model\Log $databaseLogger
     * @param \Psr\Log\LoggerInterface $systemLogger
     * @param bool|int $level
     * @param bool $bubble
     */
    public function __construct(
        \TNW\Salesforce\Model\Log $databaseLogger,
        \Psr\Log\LoggerInterface $systemLogger,
        \TNW\Salesforce\Model\Config $salesforceConfig
    ) {
        $this->databaseLogger = $databaseLogger;
        $this->systemLogger = $systemLogger;
        $this->salesforceConfig = $salesforceConfig;

        parent::__construct();
    }

    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(array|\Monolog\LogRecord $record): void
    {
        try {
            if (!$this->salesforceConfig->getDbLogStatus()) {
                return;
            }

            $message = $record['message'];
            do {
                $this->databaseLogger->setData([
                    'transaction_uid' => $record['extra']['uid'],
                    'level' => $record['level'],
                    'message' => substr($message, 0, self::MESSAGE_LIMIT_SIZE),
                ]);

                $this->databaseLogger->getResource()
                    ->save($this->databaseLogger);

                $message = substr($message, self::MESSAGE_LIMIT_SIZE);
            } while (!empty($message));

        } catch (\Exception $e) {
            $this->systemLogger->error($e);
        }
    }
}
