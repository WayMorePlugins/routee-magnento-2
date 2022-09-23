<?php

namespace Routee\WaymoreRoutee\Controller\Adminhtml\Index;

use \Magento\Backend\App\Action;
use \Magento\Backend\App\Action\Context;
use \Magento\Framework\App\Config\Storage\WriterInterface;
use Routee\WaymoreRoutee\Observer\Massapicustomers;
use Routee\WaymoreRoutee\Observer\Massapiproducts;
use Routee\WaymoreRoutee\Observer\Massapiorders;
use Routee\WaymoreRoutee\Observer\Massapiwishlists;
use Routee\WaymoreRoutee\Observer\Massapisubscribers;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class Senddata extends Action
{
    /**
     * @var Massapicustomers
     */
    protected $_massApiCustObserver;

    /**
     * @var Massapiproducts
     */
    protected $_massApiProObserver;

    /**
     * @var Massapiorders
     */
    protected $_massApiOrdObserver;

    /**
     * @var Massapiwishlists
     */
    protected $_massApiWishObserver;

    /**
     * @var Massapisubscribers
     */
    protected $_massApiSubObserver;

    /**
     * @var WriterInterface
     */
    protected $_saveConfig;

    /**
     * @var Json
     */
    protected $resultFactory;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Pool
     */
    protected $cacheFrontendPool;

    /**
     * @param Context $context
     * @param WriterInterface $configWriter
     * @param Massapicustomers $massApiCustObserver
     * @param Massapiproducts $massApiProObserver
     * @param Massapiorders $massApiordObserver
     * @param Massapiwishlists $massApiWishObserver
     * @param Massapisubscribers $massApiSubObserver
     * @param Json $response
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        Massapicustomers $massApiCustObserver,
        Massapiproducts $massApiProObserver,
        Massapiorders $massApiordObserver,
        Massapiwishlists $massApiWishObserver,
        Massapisubscribers $massApiSubObserver,
        Json $response,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        $this->_massApiCustObserver = $massApiCustObserver;
        $this->_massApiProObserver = $massApiProObserver;
        $this->_massApiOrdObserver = $massApiordObserver;
        $this->_massApiWishObserver = $massApiWishObserver;
        $this->_massApiSubObserver = $massApiSubObserver;
        $this->_saveConfig = $configWriter;
        $this->resultFactory = $response;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        parent::__construct($context);
    }

    /**
     * Is allowed
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return true;
    }

    /**
     * Execute mass data sync with routee on button click
     *
     * By Kapil Hanswal
     */
    public function execute()
    {
        if ($this->getRequest()->isAjax()) {
            $GET = $this->getRequest()->getParams();
            $result = [];
            switch ($GET['action']) {
                case 'product_data':
                    $result = $this->handleProductExport($GET);
                    break;

                case 'customer_data':
                    $result = $this->handleCustomerExport($GET);
                    break;

                case 'order_data':
                    $result = $this->handleOrderExport($GET);
                    break;

                case 'subscriber_data':
                    $result = $this->handleSubscriberExport($GET);
                    break;

                case 'wishlist_data':
                    $result = $this->handleWishlistExport($GET);
                    break;
            }

            if ($result) {
                $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $response->setData($result);
                $this->clearMagentoCache();
                return $response;
            }
        }
        return '';
    }

    private function handleProductExport($GET)
    {
        $result = $this->_massApiProObserver->getProductData($GET);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->_saveConfig->save('waymoreroutee/general/productmass', 1, 'default', 0);
        }
        return $result;
    }

    private function handleCustomerExport($GET)
    {
        $result = $this->_massApiCustObserver->getCustomerData($GET);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->_saveConfig->save('waymoreroutee/general/customermass', 1, 'default', 0);
        }
        return $result;
    }

    private function handleOrderExport($GET)
    {
        $result = $this->_massApiOrdObserver->getOrderData($GET);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->_saveConfig->save('waymoreroutee/general/ordermass', 1, 'default', 0);
        }
        return $result;
    }

    private function handleSubscriberExport($GET)
    {
        $result = $this->_massApiSubObserver->getSubscriberData($GET);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->_saveConfig->save('waymoreroutee/general/subscribermass', 1, 'default', 0);
        }
        return $result;
    }

    private function handleWishlistExport($GET)
    {
        $result = $this->_massApiWishObserver->getWishlistData($GET);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->_saveConfig->save('waymoreroutee/general/wishlistmass', 1, 'default', 0);
        }
        return $result;
    }

    /**
     * Clear Magento Cache
     */
    public function clearMagentoCache()
    {
        $types = [
            'config','layout','block_html','collections','reflection','db_ddl','eav',
            'config_integration','config_integration_api','full_page','translate','config_webservice'
        ];

        foreach ($types as $type) {
            $this->cacheTypeList->cleanType($type);
        }

        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
}
