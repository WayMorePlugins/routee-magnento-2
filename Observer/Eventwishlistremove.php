<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Customer\Model\Session;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory as WishlistCollectionFactory;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\RequestInterface;

class Eventwishlistremove implements ObserverInterface
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
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var WishlistCollectionFactory
     */
    protected $_wishlistCollectionFactory;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     * @param RequestInterface $request
     * @param WishlistCollectionFactory $wishlistCollectionFactory
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager,
        Session $customerSession,
        RequestInterface $request,
        WishlistCollectionFactory $wishlistCollectionFactory
    ) {
        $this->helper = $helper;
        $this->_storeManager = $storeManager;
        $this->_customerSession = $customerSession;
        $this->request = $request;
        $this->_wishlistCollectionFactory = $wishlistCollectionFactory;
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
            $this->helper->eventExecutedLog('WishlistDelete', 'events');

            /*Get item id and then pass into item collection to know the product id
            which is required to pass over routee json*/
            $item = $this->request->getParam('item');
            $wishlistCollection = $this->_wishlistCollectionFactory->create();
            $wishlistItemCollection = $wishlistCollection->addFieldToFilter('wishlist_item_id', ['eq' => $item]);
            foreach ($wishlistItemCollection as $wishlistData) {
                $productId = $wishlistData->getProductId();
            }
            /*Ends here*/
            $storeId    = $this->_storeManager->getStore()->getId();
            $uuid       = $this->helper->getUuid($storeId);
            $customerId = $this->_customerSession->getCustomer()->getId();
            $apiUrl     = $this->helper->getApiurl('event');
            $data       = $this->getWishlistRemoveData($uuid, $customerId, $productId);
            $this->helper->eventGrabDataLog('WishlistDelete', $data, 'events');

            $params     = $this->helper->getRequestParam('WishlistDelete', $data, $storeId);

            $this->helper->eventPayloadDataLog('WishlistDelete', $params, 'events');
            $this->helper->curl($apiUrl, $params, 'events');
        }
    }

    /**
     * Get Wishlist Remove data
     *
     * @param string $uuid
     * @param int $customerId
     * @param int $productId
     * @return array
     */
    public function getWishlistRemoveData($uuid, $customerId, $productId)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'WishlistDelete',
            'data'  => [
                'customer_id'   => $customerId,
                'product_id'    => $productId
            ]
        ];
    }
}
