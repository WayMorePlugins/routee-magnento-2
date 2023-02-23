<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;

class Eventproductoutofstock implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager
    ) {
        $this->helper = $helper;
        $this->_storeManager = $storeManager;
    }

    /**
     * Observer Event execution
     *
     * @param EventObserver $observer
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(EventObserver $observer)
    {
        $isEnabled = $this->helper->getIsEnabled();
        if ($isEnabled) {
            $this->helper->eventExecutedLog('ProductOutOfStock', 'events');

            $product    = $observer->getEvent()->getProduct();
            $id         = $product->getId(); //Get Product Id
            $storeId    = $this->_storeManager->getStore()->getId();
            $uuid       = $this->helper->getUuid($storeId);
            $apiUrl     = $this->helper->getApiurl('events');
            
            $this->helper->eventGrabDataLog('ProductOutOfStock', $product, 'events');

            $getStockItem = $product->getExtensionAttributes()->getStockItem();
            if (isset($getStockItem)) {
                $qty = $getStockItem->getQty();
            
                if ($qty < 1) {
                    $data   = $this->getOutofStockData($uuid, $id);
                    $params = $this->helper->getRequestParam('ProductOutOfStock', $data, $storeId);
                    $this->helper->eventPayloadDataLog('ProductOutOfStock', $params, 'events');

                    $this->helper->curl($apiUrl, $params, 'events');
                }
            }
        }
    }

    /**
     * Get Out of stock data
     *
     * @param string $uuid
     * @param int $id
     * @return array
     */
    public function getOutofStockData($uuid, $id)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'ProductOutOfStock',
            'data'  => [
                'product_id'   => $id
            ]
        ];
    }
}
