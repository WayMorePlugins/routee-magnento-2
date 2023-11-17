<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Sales\Api\OrderRepositoryInterface;

class Eventpaymentconfirm implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @param Data $helper
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Data $helper,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
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
            $this->helper->eventExecutedLog('OrderPaymentConfirmed', 'events');

            $invoice    = $observer->getEvent()->getInvoice();
            $order      = $invoice->getOrder();
            $storeId    = $order->getStoreId();
            $uuid       = $this->helper->getUuid($storeId);
            $customerId = $order->getCustomerId();
            $apiUrl     = $this->helper->getApiurl('event');
            $data       = $this->getOrderPaymentData($customerId, $order->getId(), $uuid);
            $this->helper->eventGrabDataLog('OrderPaymentConfirmed', $data, 'events');

            $params     = $this->helper->getRequestParam('OrderPaymentConfirmed', $data, $storeId);
            $this->helper->eventPayloadDataLog('OrderPaymentConfirmed', $params, 'events');

            $this->helper->curl($apiUrl, $params, 'events');
        }
    }

    /**
     * Get Order Payment data
     *
     * @param int $customerId
     * @param int $orderId
     * @param string $uuid
     * @return array
     */
    public function getOrderPaymentData($customerId, $orderId, $uuid)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'OrderPaymentConfirmed',
            'data'  => [
                'customer_id'   => $customerId,
                'order_id'      => $orderId
            ]
        ];
    }
}
