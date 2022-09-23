<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\Event\Manager;
use Magento\Store\Model\ScopeInterface;

/**
 * Mass data export class for wishlist data
 */
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
     * @var int
     */
    private $limit;

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
        $this->limit = $this->helper->getRPRLimit();
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
     * @return array|int[]|string
     */
    public function getWishlistData($requestData)
    {
        $uuid = $requestData['uuid'];
        $storeId = $requestData['store_id'];
        $page = $requestData['cycle_count'];
        return $this->massApiWishlistAction($uuid, 'sendMass', $storeId, 0, 0, $page);
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
    public function getWishlistCollection($callFrom, $storeId, $scopeId, $scope, $page)
    {
        $wishlistCollection = $this->_wishlistCollectionFactory->create();
        if ($scope == ScopeInterface::SCOPE_STORES) {
            $wishlistCollection = $wishlistCollection->addStoreFilter($scopeId);
        }

        if ($callFrom == 'sendMass') {
            $wishlistCollection->addStoreFilter($storeId);
            if (!empty($wishlistCollection->getData()) && $page > 0) {
                $wishlistCollection->addAttributeToSort('entity_id', 'asc')->setPageSize($this->limit)->setCurPage($page);
            }
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
     * @return string|array
     */
    public function massApiWishlistAction($uuid, $callFrom, $storeId, $scopeId, $scope, $page = 0)
    {
        $apiUrl     = $this->helper->getApiurl('massData');
        $wishlistCollection = $this->getWishlistCollection($callFrom, $storeId, $scopeId, $scope, $page);
        
        if (!empty($wishlistCollection) && count($wishlistCollection)>0) {
            $i = 0;
            $mass_data = $this->getMassData($uuid);
            foreach ($wishlistCollection as $wishlist) {
                $mass_data['data'][0]['object'][$i] = [
                    'customer_id' => $wishlist->getCustomerId(),
                    'product_id'  => $wishlist->getProductId()
                ];
                $i++;
            }
            $responseArr = $this->helper->curl($apiUrl, $mass_data);
            $result = ['reload' => 0];
            if (!empty($responseArr['message'])) {
                if ($i < $this->limit) {
                    $result = ['reload' => 1];
                }
            }
        } else {
            $result = ['reload' => 1];
        }
        return $result;
    }
}
