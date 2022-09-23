<?php
namespace Routee\WaymoreRoutee\Setup;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface as UninstallInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Config\Model\ResourceModel\Config;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;

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

    /**
     * @var StoreManagerInterface
     * This variable value assigned below in constructor
     * Added by Kapil on 20th April 2020
     */
    protected $_storeManager;
    /**
     * @var Curl
     */
    private $_curl;
    /**
     * @var Data
     */
    private $helper;

    /**
     * @param EavSetupFactory $eavSetupFactory
     * @param ModuleDataSetupInterface $mDSetup
     * @param Config $resourceConfig
     * @param Data $helper
     * @param Curl $curl
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        ModuleDataSetupInterface $mDSetup,
        Config $resourceConfig,
        Data $helper,
        Curl $curl,
        StoreManagerInterface $storeManager
    ) {
        $this->helper = $helper;
        $this->_eavSetupFactory = $eavSetupFactory;
        $this->_mDSetup = $mDSetup;
        $this->resourceConfig = $resourceConfig;
        $this->_curl = $curl;
        $this->_storeManager = $storeManager;
    }

    /**
     * Uninstall script
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     * @throws NoSuchEntityException
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $uuid = $this->helper->getUuid($storeId);

        $eventData = [];
        $eventData['data'] = [];
        $eventData['uuid'] = $uuid;
        $eventData['event'] = "Uninstall";

        $url = $this->helper->getApiurl('events');

        $this->_curl->post($url, json_encode($eventData));
        $this->_curl->addHeader("Content-Type", "application/json");
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        /*Ends here*/

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
    }
}
