<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\Event\Manager;
use Magento\Store\Model\ScopeInterface;

class Massapiwishlists implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Manager
     */
    protected $_eventManager;

    /**
     * @var CollectionFactory
     */
    protected $_wishlistCollectionFactory;

    /**
     * @param Data $helper
     * @param Manager $eventManager
     * @param CollectionFactory $wishlistCollectionFactory
     */
    public function __construct(
        Data $helper,
        Manager $eventManager,
        CollectionFactory $wishlistCollectionFactory
    ) {
        $this->helper           = $helper;
        $this->_eventManager    = $eventManager;
        $this->_wishlistCollectionFactory = $wishlistCollectionFactory;
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
            $uuid       = $observer->getData('uuid');
            $scopeId    = $observer->getData('scopeId');
            $scope      = $observer->getData('scope');
            $storeId    = $observer->getData('storeId');
            $this->massApiWishlistAction($uuid, 'eventMass', $storeId, $scopeId, $scope);
        }
    }

    /**
     * Get Wishlist data
     *
     * @param object $requestData
     * @return void
     */
    public function getWishlistData($requestData)
    {
        $uuid = $requestData['uuid'];
        $storeId = $requestData['store_id'];
        return $this->massApiWishlistAction($uuid, 'sendMass', $storeId, 0, 0);
    }

    /**
     * Get Wishlist collection
     *
     * @param string $callFrom
     * @param int $storeId
     * @param int $scopeId
     * @param int $scope
     * @return mixed
     */
    public function getWishlistCollection($callFrom, $storeId, $scopeId, $scope)
    {
        $wishlistCollection = $this->_wishlistCollectionFactory->create();
        if ($scope == ScopeInterface::SCOPE_STORES) {
            $wishlistCollection = $wishlistCollection->addStoreFilter($scopeId);
        }

        if ($callFrom == 'sendMass') {
            $wishlistCollection->addStoreFilter($storeId);
        }

        return $wishlistCollection;
    }

    /**
     * Get Mass data
     *
     * @param string $uuid
     * @return array
     */
    public function getMassData($uuid)
    {
        return [
            'uuid' => $uuid,
            'data' => [
                [
                    'name' => 'Wishlists',
                    'description' => 'Shop Wishlists'
                ]
            ]
        ];
    }

    /**
     * Wishlist Mass API action
     *
     * @param string $uuid
     * @param string $callFrom
     * @param int $storeId
     * @param int $scopeId
     * @param int $scope
     * @return string|void
     */
    public function massApiWishlistAction($uuid, $callFrom, $storeId, $scopeId, $scope)
    {
        $apiUrl     = $this->helper->getApiurl('massData');
        $wishlistCollection = $this->getWishlistCollection($callFrom, $storeId, $scopeId, $scope);
        
        if (!empty($wishlistCollection) && count($wishlistCollection)>0) {
            $w = $i = 0;
            $total_wishlists = count($wishlistCollection);
            $mass_data = $this->getMassData($uuid);
            foreach ($wishlistCollection as $wishlist) {
                $mass_data['data'][0]['object'][$i] = [
                    'customer_id' => $wishlist->getCustomerId(),
                    'product_id'  => $wishlist->getProductId()
                ];
                $i++;
                $w++;
                if ($i == 100 || $w == $total_wishlists) {
                    $responseArr = $this->helper->curl($apiUrl, $mass_data);
                    //response will contain the output in form of JSON string

                    $i = 0;
                    $mass_data['data'][0]['object'] = [];
                    if ($w == $total_wishlists) {
                        if (!empty($responseArr['message']) && $callFrom == 'eventMass') {
                            $dispatchArr = ['uuid' => $uuid, 'scopeId' => $scopeId, 'scope' => $scope];
                            $this->_eventManager->dispatch('waymoreroutee_massapiwishlist_complete', $dispatchArr);
                        } else {
                            return "WishlistDone";
                        }
                    }
                }
            }
        } else {
            return "WishlistDone";
        }
    }
}
