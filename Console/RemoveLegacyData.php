<?php

namespace Routee\WaymoreRoutee\Console;

use Routee\WaymoreRoutee\Helper\RouteeUrls;
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

    private $routee;
    public function __construct(
        Config $resourceConfig,
        RouteeUrls $routee
    ) {
        $this->resourceConfig = $resourceConfig;
        $this->routee = $routee;
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
        $this->routee->uninstallModuleCallback();
        $paths = [
            'general/enable',
            'general/username',
            'general/password',
            'general/uuid',
            'general/productmass',
            'general/customermass',
            'general/ordermass',
            'general/subscribermass',
            'general/wishlistmass',
            'url/auth',
            'url/data',
            'url/logs',
            'url/event',
        ];

        $output->writeln("Removing legacy Waymore configuration values");
        foreach ($paths as $path) {
            $this->resourceConfig->deleteConfig('waymoreroutee/'.$path);
        }
        $output->writeln("Successfully removed legacy Waymore configuration values");
    }
}
