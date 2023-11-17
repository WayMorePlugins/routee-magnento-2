<?php
namespace Routee\WaymoreRoutee\Controller\Adminhtml\Data;

use Magento\Framework\Exception\LocalizedException;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Mass data export class for orders data
 */
class OrdersMassData
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var CollectionFactory
     */
    protected $_orderCollectionFactory;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var OrderInterface
     */
    protected $order;

    /**
     * @param Data $helper
     * @param Order $orderCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param OrderInterface $order
     */
    public function __construct(
        Data $helper,
        Order $orderCollectionFactory,
        ResourceConnection $resourceConnection,
        OrderInterface $order
    ) {
        $this->helper                   = $helper;
        $this->_orderCollectionFactory  = $orderCollectionFactory;
        $this->resourceConnection       = $resourceConnection;
        $this->order = $order;
        $this->limit = $this->helper->getRPRLimit();
    }

    /**
     * Get Order data
     *
     * @param object $requestData
     * @return array
     * @throws LocalizedException
     */
    public function syncOrdersData($requestData)
    {
        $uuid = $requestData['uuid'];
        $page = $requestData['cycle_count'];
        return $this->massApiOrderAction($uuid, $page);
    }

    /**
     * Order Mass API action
     *
     * @param string $uuid
     * @param int $page
     * @return array
     * @throws LocalizedException
     */
    public function massApiOrderAction($uuid, $page = 0)
    {

        $orderCollection = $this->getorderCollection($page);
        $this->helper->eventGrabDataLog('MassOrder', count($orderCollection), 'massdata');
        if (!empty($orderCollection) && count($orderCollection) > 0) {
            $i = 0;
            $mass_data = $this->getMassData($uuid);
            foreach ($orderCollection as $orderEntity) {
                $mass_data['data'][0]['object'][$i] = $this->getOrderDetials($orderEntity);
                $i++;
            }
            $this->helper->eventPayloadDataLog('MassOrder', count($mass_data['data'][0]['object']), 'massdata');
            $apiUrl = $this->helper->getApiurl('data');
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
     * Get Order collection
     *
     * @param $page
     * @return array
     */
    public function getorderCollection($page)
    {
        $start = ($this->limit * $page) - $this->limit;
        $tableName = $this->resourceConnection->getTableName('sales_order');
        $select = "SELECT increment_id FROM $tableName ORDER BY entity_id ASC LIMIT $start, " . $this->limit;
        return $this->resourceConnection->getConnection()->fetchAll($select);
    }

    /**
     * Get Order mass data
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
                    'name' => 'Orders',
                    'description' => 'Shop Orders'
                ]
            ]
        ];
    }

    /**
     * Get Order details
     *
     * @param array $orderEntity
     * @return array
     * @throws LocalizedException
     */
    public function getOrderDetials($orderEntity)
    {
        $order = $this->getOrderObject($orderEntity);
        $payment        = $order->getPayment();
        $method         = $payment->getMethodInstance();
        $methodTitle    = $method->getTitle();
        return [
            'order_details' => [
                'customer_id'       => $order->getCustomerId(),
                'order_id'          => $order->getId(),
                'order_status'      => $order->getStatus(),
                'shipping_method'   => $order->getShippingDescription(),
                'payment_method'    => $methodTitle,
                'total_price'       => number_format($order->getGrandTotal(), 2),
                'order_create_date' => $order->getCreatedAt()
            ],
            'order_products' => $this->getOrderedProducts($order)
        ];
    }

    /**
     * @param $orderEntity
     * @return Order
     */
    public function getOrderObject($orderEntity)
    {
        return $this->order->loadByIncrementId($orderEntity['increment_id']);
    }

    /**
     * @param $order
     * @return array
     */
    private function getOrderedProducts($order)
    {
        $order_products = [];
        $orderItems = $order->getAllItems();
        foreach ($orderItems as $item) {
            $order_products[] = [
                'product_id' => $item->getProductId(),
                'quantity' => (int) $item->getQtyOrdered()
            ];
        }
        return $order_products;
    }
}
