<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;

class Eventsstockorderupdate implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Observer Event execution
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        $isEnabled = $this->helper->getIsEnabled();
        if ($isEnabled) {
            $order      = $observer->getEvent()->getOrder();
            $storeId    = $order->getStoreId();
            $uuid       = $this->helper->getUuid($storeId);
            $apiUrl     = $this->helper->getApiurl('events');
            $data       = $this->getOrderStockData($uuid, $order);

            $params     = $this->helper->getRequestParam('ProductStockUpdate', $data, $storeId);
            $this->helper->curl($apiUrl, $params);
        }
    }

    /**
     * Get Order Stock data
     *
     * @param string $uuid
     * @param object $order
     * @return array
     */
    public function getOrderStockData($uuid, $order)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'ProductStockUpdate',
            'data'  => [$this->getStockItem($order)]
        ];
    }

    /**
     * Get Stock item
     *
     * @param object $order
     * @return array
     */
    public function getStockItem($order)
    {
        $orderItems = $order->getAllItems();
        $dataStockItem = [];
        foreach ($orderItems as $item) {
            $dataStockItem = [
                'product_id' => $item->getProductId(),
                'option_id' => $item->getItemId(),
                'stock_quantity' => $item->getQtyOrdered()
            ];

        }
        return $dataStockItem;
    }
}
