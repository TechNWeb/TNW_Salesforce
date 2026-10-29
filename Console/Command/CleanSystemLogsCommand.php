<?php declare(strict_types=1);
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

namespace TNW\Salesforce\Console\Command;

use TNW\Salesforce\Cron\ClearSystemLog;
use Symfony\Component\Console\Command\Command;
use Magento\Framework\Console\Cli;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \TNW\Salesforce\Model\Synchronization\Config as SyncConfig;

/**
 * Class CleanSystemLogsCommand
 *
 * @package TNW\Salesforce\Console
 */
class CleanSystemLogsCommand extends Command
{

    /** @var ClearSystemLog */
    protected $clearSystemLogCron;

    /**
     * @var \TNW\Salesforce\Model\Config
     */
    private $salesforceConfig;

    /**
    * @var \Psr\Log\LoggerInterface
    */
    private $_logger;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @var syncConfig
     */
    private $syncConfig;

    /** @var \Magento\Framework\Filesystem\Driver\File  */
    protected $file;

    /**
     * CleanSystemLogsCommand constructor.
     */
    public function __construct(
        ClearSystemLog $clearSystemLogCron,
        \TNW\Salesforce\Model\Config $salesforceConfig,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Filesystem\Driver\File $file,
        TimezoneInterface $timezone,
        SyncConfig $syncConfig
    )
    {
        $this->_logger = $logger;
        $this->file = $file;
        $this->timezone = $timezone;
        $this->salesforceConfig = $salesforceConfig;
        $this->clearSystemLogCron = $clearSystemLogCron;
        $this->syncConfig = $syncConfig;
        parent::__construct();
    }

    public function configure()
    {

        $this->setName('tnw_salesforce:clean_system_logs')
            ->setDescription('Clear the old system logs files.');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {

        try {
            if (!$this->salesforceConfig->getSalesforceStatus()) {
                return Cli::RETURN_SUCCESS;
            }

            if (!$this->salesforceConfig->getClearSystemLogs()) {
                $output->writeln($this->getDateTime() . ': ' .' Clear System logs not configured.');
                $this->_logger->info($this->getDateTime() . ': ' .' Clear System logs not configured');
                return Cli::RETURN_SUCCESS;
            }

            // save to config time when cron was executed
            $this->syncConfig->setGlobalLastCronRun(
                $this->syncConfig->getMagentoTime(),
                    SyncConfig::CLEAN_SYSTEM_LOGS
            );

            $executeClearDebuglog = $this->clearSystemLogCron->execute();

            if($executeClearDebuglog){
                $output->writeln($this->getDateTime() . ': ' .'Cleared logs successfully.');
            }


        } catch (\Exception $e) {
            $output->writeln($this->getDateTime() . ': ' . $e->getMessage());
        }

        return Cli::RETURN_SUCCESS;
    }


    /**
     * @return string
     */
    public function getDateTime()
    {
        return $this->timezone->date()->format('m/d/y H:i:s');
    }

}
