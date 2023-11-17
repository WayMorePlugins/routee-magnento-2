<?php
namespace Routee\WaymoreRoutee\Setup;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface as UninstallInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Config\Model\ResourceModel\Config;
use Routee\WaymoreRoutee\Helper\RouteeUrls;

/**
 * Uninstall class
 */
class Uninstall implements UninstallInterface
{


    /**
     * @var EavSetupFactory
     */
    private $_eavSetupFactory;

    /**
     * @var ModuleDataSetupInterface
     */
    private $_mDSetup;

    /**
     * @var ConfigInterface
     */
    protected $resourceConfig;

    protected $_storeManager;

    /**
     * @var RouteeUrls
     */
    private $urlHelper;

    /**
     * @param EavSetupFactory $eavSetupFactory
     * @param ModuleDataSetupInterface $mDSetup
     * @param Config $resourceConfig
     * @param RouteeUrls $routeeUrls
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        ModuleDataSetupInterface $mDSetup,
        Config $resourceConfig,
        RouteeUrls $routeeUrls
    ) {
        $this->_eavSetupFactory = $eavSetupFactory;
        $this->_mDSetup = $mDSetup;
        $this->resourceConfig = $resourceConfig;
        $this->urlHelper = $routeeUrls;
    }

    /**
     * Uninstall script
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $this->urlHelper->uninstallModuleCallback();

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
        foreach ($paths as $path) {
            $this->resourceConfig->deleteConfig('waymoreroutee/'.$path);
        }
    }
}
