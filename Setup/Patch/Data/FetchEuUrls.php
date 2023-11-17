<?php
namespace Routee\WaymoreRoutee\Setup\Patch\Data;

use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Config\Model\ResourceModel\Config;
use Routee\WaymoreRoutee\Helper\RouteeUrls;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class FetchEuUrls implements DataPatchInterface, PatchRevertableInterface
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
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param Config $resourceConfig
     * @param RouteeUrls $routeeUrls
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        Config $resourceConfig,
        RouteeUrls $routeeUrls,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->resourceConfig = $resourceConfig;
        $this->routeeUrls = $routeeUrls;
        $this->moduleDataSetup = $moduleDataSetup;
    }
    
   /**
    * @inheritdoc
    */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->routeeUrls->deleteConfig();
       
       //Fetch and save Routee URLs
        $this->routeeUrls->routeeSaveUrls();
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * Parent method used upon uninstallation to revert whatever was added in apply method
     * @return void
     */
    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->routeeUrls->uninstallModuleCallback();
        $this->routeeUrls->deleteConfig();
        $this->moduleDataSetup->getConnection()->endSetup();
    }
    
    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

   /**
    * @inheritdoc
    */
    public function getAliases()
    {
        return [];
    }

   /**
    * @inheritdoc
    */
    public static function getVersion()
    {
        return '1.0.0';
    }
}
