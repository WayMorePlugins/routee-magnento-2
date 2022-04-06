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
    protected $_massApiproObserver;

    /**
     * @var Massapiorders
     */
    protected $_massApiordObserver;

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
     * @param Massapiproducts $massApiproObserver
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
        Massapiproducts $massApiproObserver,
        Massapiorders $massApiordObserver,
        Massapiwishlists $massApiWishObserver,
        Massapisubscribers $massApiSubObserver,
        Json $response,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        $this->_massApiCustObserver = $massApiCustObserver;
        $this->_massApiproObserver = $massApiproObserver;
        $this->_massApiordObserver = $massApiordObserver;
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
            $this->_massApiCustObserver->getCustomerData($GET);
            $this->_massApiSubObserver->getSubscriberData($GET);
            $this->_massApiproObserver->getProductData($GET);
            $this->_massApiWishObserver->getWishlistData($GET);
            $orderData = $this->_massApiordObserver->getOrderData($GET);
            if ($orderData == 'OrderDone') {
                $this->_saveConfig->save('waymoreroutee/general/datatransferred', $GET['uuid'], 'default', 0);
                $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $response->setData(["msg" => "IntegrationDone"]);
                $this->clearMagentoCache();
                return $response;
            }
        }
        return '';
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
