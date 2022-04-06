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
            $invoice    = $observer->getEvent()->getInvoice();
            $storeId    = $invoice->getStoreId();
            $uuid       = $this->helper->getUuid($storeId);
            $order      = $this->orderRepository->get($invoice->getOrderId());
            $customerId = $order->getCustomerId();
            $apiUrl     = $this->helper->getApiurl('events');
            $data       = $this->getOrderPaymentData($customerId, $invoice, $uuid);

            $params     = $this->helper->getRequestParam('OrderPaymentConfirmed', $data, $storeId);
            $this->helper->curl($apiUrl, $params);
        }
    }

    /**
     * Get Order Payment data
     *
     * @param int $customerId
     * @param object $invoice
     * @param string $uuid
     * @return array
     */
    public function getOrderPaymentData($customerId, $invoice, $uuid)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'OrderPaymentConfirmed',
            'data'  => [
                'customer_id'   => $customerId,
                'order_id'      => $invoice->getOrderId()
            ]
        ];
    }
}
