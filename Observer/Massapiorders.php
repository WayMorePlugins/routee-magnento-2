<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\ScopeInterface;

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
     * @param WriterInterface $configWriter
     * @param Data $helper
     * @param CollectionFactory $orderCollectionFactory
     */
    public function __construct(
        WriterInterface $configWriter,
        Data $helper,
        CollectionFactory $orderCollectionFactory
    ) {
        $this->configWriter             = $configWriter;
        $this->helper                   = $helper;
        $this->_orderCollectionFactory  = $orderCollectionFactory;
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
    public function getorderCollection($callFrom, $scope, $scopeId, $storeId)
    {
        $orderCollection = $this->_orderCollectionFactory->create()->addAttributeToSelect('*');
        if ($scope == ScopeInterface::SCOPE_STORES && $callFrom == 'eventMass') {
            $orderCollection = $orderCollection->addStoreFilter($scopeId);
        } else {
            $orderCollection = $orderCollection->addAttributeToFilter("store_id", ["eq" => $storeId]);
        }
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
        return $this->massApiOrderAction('eventRequest', $requestData['uuid'], 0, 0, $requestData['store_id']);
    }

    /**
     * Order Mass API action
     *
     * @param string $callFrom
     * @param string $uuid
     * @param int $scopeId
     * @param object $scope
     * @param int $storeId
     * @return string|void
     */
    public function massApiOrderAction($callFrom, $uuid, $scopeId, $scope, $storeId)
    {
        $apiUrl     = $this->helper->getApiurl('massData');
        $orderCollection = $this->getorderCollection($callFrom, $scope, $scopeId, $storeId);

        if (!empty($orderCollection) && count($orderCollection) > 0) {
            $o = $i = 0;

            $total_orders = count($orderCollection);
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
                $o++;

                if ($i == 100 || $o == $total_orders) {
                    $responseArr = $this->helper->curl($apiUrl, $mass_data);
                    //response will contain the output in form of JSON string

                    $i = 0;
                    $mass_data['data'][0]['object'] = [];

                    if ($o == $total_orders) {
                        if (!empty($responseArr['message']) && $callFrom == 'eventMass') {
                            $this->configWriter->save('waymoreroutee/general/datatransferred', $uuid, 'default', 0);
                        } else {
                            return "OrderDone";
                        }
                    }
                }
            }
        } else {
            return "OrderDone";
        }
    }
}
