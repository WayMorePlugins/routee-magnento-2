<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory;
use Magento\Newsletter\Model\Subscriber;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Newsletter\Model\SubscriberFactory;

/**
 * Mass data export class for Newsletter subscribers data
 */
class Massapisubscribers implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var CollectionFactory
     */
    protected $_subscriberCollectionFactory;

    /**
     * @var int
     */
    private $limit;

	/**
     * @var SubscriberFactory
     */
    private $subscriberFactory;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param Data $helper
     * @param Subscriber $subcriberCollectionFactory
     * @param ResourceConnection $resourceConnection
     * @param SubscriberFactory $subscriberFactory
     */
    public function __construct(
        Data $helper,
        Subscriber $subscriberCollectionFactory,
		ResourceConnection $resourceConnection,
		SubscriberFactory $subscriberFactory
    ) {
        $this->helper   = $helper;
        $this->_subscriberCollectionFactory = $subscriberCollectionFactory;
		$this->resourceConnection = $resourceConnection;
		$this->subscriberFactory = $subscriberFactory;
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
            $this->helper->eventExecutedLog('MassSubscriber', 'massdata');

            $uuid       = $observer->getData('uuid');
            $scopeId    = $observer->getData('scopeId');
            $scope      = $observer->getData('scope');
            $storeId    = $observer->getData('storeId');
            $this->massApiSubscriberAction($uuid, 'eventMass', $storeId, $scopeId, $scope);
        }
    }

    /**
     * Get Subscriber data
     *
     * @param object $requestData
     * @return string|void
     */
    public function getSubscriberData($requestData)
    {
        $uuid = $requestData['uuid'];
        $storeId = $requestData['store_id'];
        $page = $requestData['cycle_count'];
        return $this->massApiSubscriberAction($uuid, 'sendMass', $storeId, 0, 0, $page);
    }

    /**
     * Get Subscriber Collection
     *
     * @param string $callFrom
     * @param int $storeId
     * @param int $scopeId
     * @param int $scope
     * @return \Magento\Newsletter\Model\ResourceModel\Subscriber\Collection
     */
    public function getSubscriberCollection($callFrom, $storeId, $scopeId, $scope, $page)
    {
		$tableName = $this->resourceConnection->getTableName('newsletter_subscriber');
        $select = $this->resourceConnection->getConnection()
            ->select()
            ->from($tableName, '*')
            ->order('subscriber_id', 'asc')
            ->limit($this->limit, ($page - 1) * $this->limit);
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
                    'name' => 'Subscribers',
                    'description' => 'Shop Subscribers'
                ]
            ]
        ];
    }

    /**
     * Get Subscriber Information
     *
     * @param object $subscriber
     * @param int $scopeId
     * @param int $storeId
     * @param string $callFrom
     * @return array[]
     */
    public function getSubscriberInfo($subscriber, $scopeId, $storeId, $callFrom)
    {
        $allowedId = $callFrom == 'sendMass' ?  $storeId : $scopeId;
        return [
            'subscriber' => [
                'id'                         => $subscriber->getSubscriberId(),
                'id_shop'                    => $allowedId,
                'id_shop_group'              => $allowedId,
                'email'                      => $subscriber->getSubscriberEmail(),
                'newsletter_date_add'        => $subscriber->getChangeStatusAt(),
                'ip_registration_newsletter' => 'N/A',
                'http_referer'               => 'N/A',
                'active'                     => $subscriber->getSubscriberStatus(),
            ]
        ];
    }

    /**
     * Subscriber Mass API acrion
     *
     * @param string $uuid
     * @param string $callFrom
     * @param int $storeId
     * @param int $scopeId
     * @param int $scope
     * @return string|array
     */
    public function massApiSubscriberAction($uuid, $callFrom, $storeId, $scopeId, $scope, $page = 0)
    {
        $apiUrl     = $this->helper->getApiurl('massData');
        $subscriberCollection = $this->getSubscriberCollection($callFrom, $storeId, $scopeId, $scope, $page);

        $this->helper->eventGrabDataLog('MassSubscriber', count($subscriberCollection), 'massdata');

        if (!empty($subscriberCollection) && count($subscriberCollection)>0) {
            $i = 0;
            $mass_data = $this->getMassData($uuid);
            foreach ($subscriberCollection as $subscriber) {
				$subscriber = $this->subscriberFactory->create()->loadByCustomerId((int)$subscriber['customer_id']);
					
                $subscriberInfo = $this->getSubscriberInfo($subscriber, $scopeId, $storeId, $callFrom);
                $mass_data['data'][0]['object'][$i] = $subscriberInfo;
                $i++;
            }

            $this->helper->eventPayloadDataLog('MassSubscriber', count($mass_data['data'][0]['object']), 'massdata');

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
}
