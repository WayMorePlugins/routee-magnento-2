<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Sales\Model\Order;

/**
 * Mass data export class for orders data
 */
class Massapiorders implements ObserverInterface
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

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
     * @param WriterInterface $configWriter
     * @param Data $helper
     * @param Order $orderCollectionFactory
     */
    public function __construct(
        WriterInterface $configWriter,
        Data $helper,
        Order $orderCollectionFactory
    ) {
        $this->configWriter             = $configWriter;
        $this->helper                   = $helper;
        $this->_orderCollectionFactory  = $orderCollectionFactory;
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
            $this->helper->eventExecutedLog('MassOrder', 'massdata');

            $uuid       = $observer->getData('uuid');
            $scopeId    = $observer->getData('scopeId');
            $scope      = $observer->getData('scope');
            $storeId    = $observer->getData('storeId');
            $this->massApiOrderAction('eventMass', $uuid, $scopeId, $scope, $storeId);
        }
    }

    /**
     * Get Order collection
     *
     * @param string $callFrom
     * @param object $scope
     * @param int $scopeId
     * @param int $storeId
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getorderCollection($callFrom, $scope, $scopeId, $storeId, $page)
    {
        $orderCollection = $this->_orderCollectionFactory->getCollection();
		$orderCollection->setOrder(
                'entity_id',
                'asc'
            )->setPageSize($this->limit)->setCurPage($page);
		
        return $orderCollection;
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
     * @param object $order
     * @return array[]
     */
    public function getOrderDetials($order)
    {
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
                'total_price'       => $order->getGrandTotal(),
                'order_create_date' => $order->getCreatedAt()
            ]
        ];
    }

    /**
     * Get Order data
     *
     * @param object $requestData
     * @return string|void
     */
    public function getOrderData($requestData)
    {
        $uuid = $requestData['uuid'];
        $storeId = $requestData['store_id'];
        $page = $requestData['cycle_count'];
        return $this->massApiOrderAction('eventRequest', $uuid, 0, 0, $storeId, $page );
    }

    /**
     * Order Mass API action
     *
     * @param string $callFrom
     * @param string $uuid
     * @param int $scopeId
     * @param object $scope
     * @param int $storeId
     * @return string|array
     */
    public function massApiOrderAction($callFrom, $uuid, $scopeId, $scope, $storeId, $page = 0)
    {
        $apiUrl     = $this->helper->getApiurl('massData');
        $orderCollection = $this->getorderCollection($callFrom, $scope, $scopeId, $storeId, $page);

        $this->helper->eventGrabDataLog('MassOrder', count($orderCollection), 'massdata');

        if (!empty($orderCollection) && count($orderCollection) > 0) {
            $i = 0;
            $mass_data = $this->getMassData($uuid);

            foreach ($orderCollection as $order) {
                $mass_data['data'][0]['object'][$i] = $this->getOrderDetials($order);
                $orderItems = $order->getAllItems();
                foreach ($orderItems as $item) {
                    $mass_data['data'][0]['object'][$i]['order_products'][] = [
                        'product_id' => $item->getProductId(),
                        'quantity' => $item->getQtyOrdered(),
                    ];
                }
                $i++;
            }

            $this->helper->eventPayloadDataLog('MassOrder', count($mass_data['data'][0]['object']), 'massdata');

            $responseArr = $this->helper->curl($apiUrl, $mass_data, 'data');

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
