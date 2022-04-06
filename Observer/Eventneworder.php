<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Directory\Model\CountryFactory;
use Magento\Newsletter\Model\Subscriber;

class Eventneworder implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var CountryFactory
     */
    protected $_countryFactory;

    /**
     * @var Subscriber
     */
    protected $_subscriber;

    /**
     * @param Data $helper
     * @param CountryFactory $countryFactory
     * @param Subscriber $subscriber
     */
    public function __construct(
        Data $helper,
        CountryFactory $countryFactory,
        Subscriber $subscriber
    ) {
        $this->helper = $helper;
        $this->_countryFactory = $countryFactory;
        $this->_subscriber = $subscriber;
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
            $customerId = $order->getCustomerId();
            $apiUrl     = $this->helper->getApiurl('events');
            $checkSubscriber    = ($this->_subscriber->loadByEmail($order->getCustomerEmail()))?'true':'false';

            $orderDetails       = $this->getOrderDetails($order, $checkSubscriber);
            $orderProducts      = $this->getOrderProducts($order);
            $data               = $this->getOrderData($uuid, $customerId, $orderDetails, $orderProducts);
            $params             = $this->helper->getRequestParam('OrderAdd', $data, $storeId);
            $this->helper->curl($apiUrl, $params);
        }
    }

    /**
     * Get order details
     *
     * @param object $order
     * @param string $checkSubscriber
     * @return array
     */
    public function getOrderDetails($order, $checkSubscriber)
    {
        $payment            = $order->getPayment();
        $methodPayment      = $payment->getMethodInstance();
        $paymentTitle       = $methodPayment->getTitle();
        $billingAddress     = $order->getBillingAddress()->getData();
        $country            = $this->_countryFactory->create()->loadByCode($billingAddress['country_id']);
        $countryNameBilling = $country->getName();
        return [
            'order_id'          => $order->getIncrementId(),
            'order_status'      => $order->getStatusLabel(),
            'shipping_method'   => $order->getShippingDescription(),
            'payment_method'    => $paymentTitle,
            'total_price'       => $order->getGrandTotal(),
            'lastname'          => $order->getCustomerLastname(),
            'firstname'         => $order->getCustomerFirstname(),
            'birthday'          => $order->getCustomerDob(),
            'email'             => $order->getCustomerEmail(),
            'company'           => $billingAddress['company'],
            'newsletter'        => $checkSubscriber,
            'website'           => 'null',
            'address1'          => implode(" ", $order->getBillingAddress()->getStreet(0)),
            'address2'          => implode(" ", $order->getBillingAddress()->getStreet(1)),
            'postcode'          => $billingAddress['postcode'],
            'phone'             => $billingAddress['telephone'],
            'phone_mobile'      => '',
            'city'              => $billingAddress['city'],
            'country'           => $countryNameBilling
        ];
    }

    /**
     * Get order products
     *
     * @param object $order
     * @return array
     */
    public function getOrderProducts($order)
    {
        $orderProducts = [];
        $orderItems = $order->getAllItems();
        foreach ($orderItems as $item) {
            $orderProducts[] = [
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQtyOrdered(),
                'option_id' => $item->getItemId()
            ];
        }
        return $orderProducts;
    }

    /**
     * Get Order Data
     *
     * @param string $uuid
     * @param int $customerId
     * @param array $orderDetails
     * @param array $orderProducts
     * @return array
     */
    public function getOrderData($uuid, $customerId, $orderDetails, $orderProducts)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'OrderAdd',
            'data'  => [
                'customer_id'   => $customerId,
                'order_details' => $orderDetails,
                'order_products'=> $orderProducts
            ]
        ];
    }
}
