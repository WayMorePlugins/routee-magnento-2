<?php
namespace Routee\WaymoreRoutee\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface as UninstallInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Config\Model\ResourceModel\Config;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    protected $resourceConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     * This variable value asisgned below in constructor
     * Added by Kapil on 20th April 2020
     */
    protected $_storeManager;

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
        //Added by Kapil on 20th April 2020
        $this->_storeManager = $storeManager;
    }

    /**
     * Uninstall script
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $uuid = $this->helper->getUuid($storeId);
        
        /*
        * Added to remove data from routee database when calling uninstall event
        * Also added curl library in constructor to use below.
        * Created on 26th Feb 2020
        * By : Kapil Hanswal
        */
        $eventdata = [];
        $eventdata['data'] = [];
        $eventdata['uuid'] = $uuid;
        $eventdata['event'] = "Uninstall";

        $url = $this->helper->getApiurl('events');

        $this->_curl->post($url, json_encode($eventdata));
        $this->_curl->addHeader("Content-Type", "application/json");
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        /*Ends here*/

        /**
        * Below code deleting config details from the database so next time after reinstallation
        * user have to submit again to generate UUID
        * Initially it was not removing due to wrong config library but included correct one
        *
        * Removed all the unnecessary code from this script
        * Added by Kapil on 21st April 2020
        */
        $this->resourceConfig->deleteConfig('waymoreroutee/general/enable');
        $this->resourceConfig->deleteConfig('waymoreroutee/general/username');
        $this->resourceConfig->deleteConfig('waymoreroutee/general/password');
        $this->resourceConfig->deleteConfig('waymoreroutee/general/uuid');
        $this->resourceConfig->deleteConfig('waymoreroutee/general/testmode');
        $this->resourceConfig->deleteConfig('waymoreroutee/general/datatransferred');
    }
}
