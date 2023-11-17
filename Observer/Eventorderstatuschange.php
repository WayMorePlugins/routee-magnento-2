<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;

class Eventorderstatuschange implements ObserverInterface
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
            $this->helper->eventExecutedLog('OrderStatusUpdate', 'events');

            $order      = $observer->getEvent()->getOrder();
            $storeId    = $order->getStoreId();
            $uuid       = $this->helper->getUuid($storeId);
            $customerId = $order->getCustomerId();
            $apiUrl     = $this->helper->getApiurl('event');

            $data       = $this->getOrderData($customerId, $uuid, $order);
            $this->helper->eventGrabDataLog('OrderStatusUpdate', $data, 'events');

            $params     = $this->helper->getRequestParam('OrderStatusUpdate', $data, $storeId);
            $this->helper->eventPayloadDataLog('OrderStatusUpdate', $params, 'events');
            $this->helper->curl($apiUrl, $params, 'events');
        }
    }

    /**
     * Get Order data
     *
     * @param int $customerId
     * @param string $uuid
     * @param object $order
     * @return array
     */
    public function getOrderData($customerId, $uuid, $order)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'OrderStatusUpdate',
            'data'  => [
                'customer_id'   => $customerId,
                'order_id'      => $order->getId(),
                'order_status'  => $order->getStatusLabel()
            ]
        ];
    }
}
