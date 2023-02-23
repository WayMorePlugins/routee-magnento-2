<?php

namespace Routee\Waymore\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Config\Model\ResourceModel\Config;

class InstallData implements InstallDataInterface
{
    private $resourceConfig;

    /**
     * @param Config
     */
    public function __construct(Config $resourceConfig)
    {
        $this->resourceConfig = $resourceConfig;
    }

    /**
     * @param  ModuleDataSetupInterface
     * @param  ModuleContextInterface
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
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
        foreach ($paths as $path) {
            $this->resourceConfig->deleteConfig('waymoreroutee/general/'.$path);
        }
        $installer->endSetup();
    }
}
