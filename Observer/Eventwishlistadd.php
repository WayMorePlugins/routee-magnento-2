<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Customer\Model\Session;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;

class Eventwishlistadd implements ObserverInterface
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
     * @var Session
     */
    protected $_customerSession;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager,
        Session $customerSession
    ) {
        $this->helper = $helper;
        $this->_storeManager = $storeManager;
        $this->_customerSession = $customerSession;
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
            $this->helper->eventExecutedLog('WishlistAdd', 'events');

            $wishlistItem   = $observer->getItems();
            $storeId        = $this->_storeManager->getStore()->getId();
            $uuid           = $this->helper->getUuid($storeId);
            $customerId     = $this->_customerSession->getCustomer()->getId();
            $apiUrl         = $this->helper->getApiurl('event');
            $data           = $this->getWishlistData($uuid, $customerId, $wishlistItem);
            $this->helper->eventGrabDataLog('WishlistAdd', $data, 'events');

            $params         = $this->helper->getRequestParam('WishlistAdd', $data, $storeId);

            $this->helper->eventPayloadDataLog('WishlistAdd', $params, 'events');
            $this->helper->curl($apiUrl, $params, 'events');
        }
    }

    /**
     * Get Wishlist Data
     *
     * @param string $uuid
     * @param int $customerId
     * @param object $wishlistItem
     * @return array
     */
    public function getWishlistData($uuid, $customerId, $wishlistItem)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'WishlistAdd',
            'data'  => [
                'customer_id'   => $customerId,
                'product_id'    => $wishlistItem[0]->getBuyRequest()->getProduct()
            ]
        ];
    }
}
