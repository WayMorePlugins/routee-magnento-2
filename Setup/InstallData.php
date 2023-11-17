<?php

namespace Routee\WaymoreRoutee\Setup;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Config\Model\ResourceModel\Config;
use Routee\WaymoreRoutee\Helper\RouteeUrls;

class InstallData implements InstallDataInterface
{
    /**
     * @var Config
     */
    private $resourceConfig;

    /**
     * @var RouteeUrls
     */
    private $routeeUrls;

    /**
     * @param Config $resourceConfig
     * @param RouteeUrls $routeeUrls
     */
    public function __construct(
        Config $resourceConfig,
        RouteeUrls $routeeUrls
    )
    {
        $this->resourceConfig = $resourceConfig;
        $this->routeeUrls = $routeeUrls;
    }

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

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
            'url/event'
        ];
        foreach ($paths as $path) {
            $this->resourceConfig->deleteConfig('waymoreroutee/' . $path);
        }
        //Fetch and save Routee URLs
        $this->routeeSaveUrls();
        $installer->endSetup();
    }

    /**
     * @return void
     */
    public function routeeSaveUrls()
    {
        $urls = $this->routeeUrls->fetchUrls();
        if(isset($urls['data']) && $urls['success'] == 1){
            foreach ($urls['data'] as $url){
                $this->resourceConfig->saveConfig('waymoreroutee/url/'.$url['type'], $url['url']);
            }
        }
    }
}
