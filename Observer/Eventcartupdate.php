<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\UrlInterface;

class Eventcartupdate implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var UrlInterface
     */
    protected $_urlInterface;

    /**
     * @param Data $helper
     * @param UrlInterface $urlInterface
     */
    public function __construct(
        Data $helper,
        UrlInterface $urlInterface
    ) {
        $this->helper = $helper;
        $this->_urlInterface = $urlInterface;
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
        //getStoreId
        if ($isEnabled) {
            $this->helper->eventExecutedLog('CartUpdate', 'events');

            $apiUrl = $this->helper->getApiurl('events');
            $params = $this->getRequestParam($observer);

            $this->helper->eventPayloadDataLog('CartUpdate', $params, 'events');
            $this->helper->curl($apiUrl, $params, 'events');
        }
    }

    /**
     * Get API request parameters
     *
     * @param object $observer
     * @return array
     */
    public function getRequestParam($observer)
    {
        $cart       = $observer->getCart();
        $allitems   =  $cart->getQuote()->getAllVisibleItems();
        $storeId    = $cart->getQuote()->getStoreId();
        $uuid       = $this->helper->getUuid($storeId);
        $customerId = $cart->getQuote()->getCustomerId() > 1 ? $cart->getQuote()->getCustomerId() : 0;

        $data = $cartDetails = $itemOptions = [];

        $data['uuid'] = $uuid;
        $data['event'] = 'CartUpdate';
        $data['data'][]['customer_id'] = $customerId;
        foreach ($allitems as $item) {
            foreach ($item->getOptions() as $option) {
                $itemOptions[] = $option['option_id'];
            }
            $cartDetails['product_id'] = $item->getProductId();
            $cartDetails['option_id'] = implode(",", $itemOptions);
            $cartDetails['quantity'] = $item->getQty();
            $data['data'][]['cart_products'][] = $cartDetails;
        }
        $data['data'][]['cart_url'] = $this->_urlInterface->getUrl('checkout/cart');

        $this->helper->eventGrabDataLog('CartUpdate', $data, 'events');

        return [
            'uuid' => $uuid,
            'event' => "CartUpdate",
            'data' => $data['data']
        ];
    }
}
