<?php
/**
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace TNW\Salesforce\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use TNW\Salesforce\Service\Model\Grid\GetGridUpdatersByEntityTypes;

/**
 *  Class RefreshSalesforceDataOnGrids
 */
class RefreshSalesforceDataOnGrids extends Command
{
    /** @var GetGridUpdatersByEntityTypes */
    private $getGridUpdatersByEntityTypes;

    /**
     * @param GetGridUpdatersByEntityTypes $getGridUpdatersByEntityTypes
     * @param string|null                  $name
     */
    public function __construct(
        GetGridUpdatersByEntityTypes $getGridUpdatersByEntityTypes,
        ?string                       $name = null
    )
    {
        parent::__construct($name);
        $this->getGridUpdatersByEntityTypes = $getGridUpdatersByEntityTypes;
    }

    /**
     * @inheritDoc
     */
    public function configure()
    {
        $this->setName('tnw_salesforce:refresh_grids')
            ->setDescription('Refresh salesforce data for grids.');

        parent::configure();
    }

    /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            foreach ($this->getGridUpdatersByEntityTypes->execute() as $updaters) {
                foreach ($updaters as $updater) {
                    $updater->execute();
                }
            }
        } catch (Throwable $e) {
            $output->writeln($e->getMessage());
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }
}
