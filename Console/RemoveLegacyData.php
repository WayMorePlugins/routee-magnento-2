<?php

namespace Routee\WaymoreRoutee\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Magento\Config\Model\ResourceModel\Config;

class RemoveLegacyData extends Command
{
    /**
     * @var Config
     */
    protected $resourceConfig;

    /**
     * @param Config $resourceConfig
     */
    public function __construct(Config $resourceConfig)
    {
        $this->resourceConfig = $resourceConfig;
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('waymore:removelegacydata');
        $this->setDescription('Remove WayMore installation data.');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $paths = [
            'enable',
            'username',
            'password',
            'uuid',
            'productmass',
            'customermass',
            'ordermass',
            'subscribermass',
            'wishlistmass',
        ];

        $output->writeln("Removing legacy Waymore configuration values");
        foreach ($paths as $path) {
            $this->resourceConfig->deleteConfig('waymoreroutee/general/'.$path);
        }
        $output->writeln("Successfully removed legacy Waymore configuration values");
    }
}