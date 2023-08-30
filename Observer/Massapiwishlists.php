<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\Event\Manager;
use Magento\Store\Model\ScopeInterface;
use Magento\Wishlist\Model\Wishlist;
use Magento\Framework\App\ResourceConnection;

/**
 *Mass data export class for wishlist data
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
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Data $helper
     * @param Manager $eventManager
     * @param CollectionFactory $wishlistCollectionFactory
     */
    public function __construct(
        Data $helper,
        Manager $eventManager,
        Wishlist $wishlistCollectionFactory,
		ResourceConnection $resourceConnection
    ) {
        $this->helper           = $helper;
        $this->_eventManager    = $eventManager;
        $this->_wishlistCollectionFactory = $wishlistCollectionFactory;
		$this->resourceConnection = $resourceConnection;
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
            $this->helper->eventExecutedLog('MassWishlist', 'massdata');

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
        $start = ($this->limit * $page) - $this->limit;

        $tableName = $this->resourceConnection->getTableName('wishlist');
        $select = $this->resourceConnection->getConnection()
            ->select()
            ->from($tableName, '*')
            ->order('wishlist_id', 'asc')
            ->limit($this->limit, $start);
        return $this->resourceConnection->getConnection()->fetchAll($select);
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
        $apiUrl  = $this->helper->getApiurl('massData');
        $wishlistCollection = $this->getWishlistCollection($callFrom, $storeId, $scopeId, $scope, $page);

        $this->helper->eventGrabDataLog('MassWishlist', count($wishlistCollection), 'massdata');

        if (!empty($wishlistCollection) && count($wishlistCollection) > 0) {
            $i = 0;
            $mass_data = $this->getMassData($uuid);
            foreach ($wishlistCollection as $wishlist) {

                $mass_data['data'][0]['object'][$i] = [
                    'customer_id' => $wishlist['customer_id'],
                    'product_id'  => $this->getWishlistProductIds($wishlist['wishlist_id'])
                ];
                $i++;
            }
            $this->helper->eventPayloadDataLog('MassWishlist', count($mass_data['data'][0]['object']), 'massdata');
            $responseArr = $this->helper->curl($apiUrl, $mass_data, 'massData');

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

    /**
     * Wishlist get Mass Products
     *
     * @param int $wishlistId
     * @return array
     */
	public function getWishlistProductIds($wishlistId)
	{
		$tableName = $this->resourceConnection->getTableName('wishlist_item');
        $select = $this->resourceConnection->getConnection()
            ->select()
            ->from($tableName, 'product_id')
			->where("wishlist_id = $wishlistId");
        $wishlistItems = $this->resourceConnection->getConnection()->fetchAll($select);


		$pIds = [];
		foreach ($wishlistItems as $item) {
			$pIds[] = $item['product_id'];
		}
		return $pIds;
	}
}
