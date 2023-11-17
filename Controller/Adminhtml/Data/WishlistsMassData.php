<?php
namespace Routee\WaymoreRoutee\Controller\Adminhtml\Data;

use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\App\ResourceConnection;

/**
 * Mass data export class for wishlist data
 */
class WishlistsMassData
{
    /**
     * @var Data
     */
    protected $helper;

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
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Data $helper,
        ResourceConnection $resourceConnection
    ) {
        $this->helper           = $helper;
        $this->resourceConnection = $resourceConnection;
        $this->limit = $this->helper->getRPRLimit();
    }

    /**
     * Get Wishlist data
     *
     * @param object $requestData
     * @return array
     */
    public function syncWishlistsData($requestData)
    {
        $uuid = $requestData['uuid'];
        $page = $requestData['cycle_count'];
        return $this->massApiWishlistAction($uuid, $page);
    }

    /**
     * Wishlist Mass API action
     *
     * @param string $uuid
     * @param int $page
     * @return array
     */
    public function massApiWishlistAction($uuid, $page = 0)
    {
        $wishlistCollection = $this->getWishlistCollection($page);
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
            $apiUrl  = $this->helper->getApiurl('data');
            $responseArr = $this->helper->curl($apiUrl, $mass_data, 'massdata');

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
     * Get Wishlist collection
     *
     * @param $page
     * @return array
     */
    public function getWishlistCollection($page)
    {
        $start = ($this->limit * $page) - $this->limit;
        $tableName = $this->resourceConnection->getTableName('wishlist');
        $select = "SELECT wishlist_id, customer_id FROM $tableName ORDER BY wishlist_id ASC LIMIT $start, " . $this->limit;
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
     * Wishlist get Mass Products
     *
     * @param int $wishlistId
     * @return array
     */
    public function getWishlistProductIds($wishlistId)
    {
        $tableName = $this->resourceConnection->getTableName('wishlist_item');
        $select = "SELECT product_id FROM $tableName WHERE wishlist_id = $wishlistId";
        $wishlistItems = $this->resourceConnection->getConnection()->fetchAll($select);
        $pIds = [];
        foreach ($wishlistItems as $item) {
            $pIds[] = $item['product_id'];
        }
        return $pIds;
    }
}
