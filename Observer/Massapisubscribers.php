<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory;
use Magento\Store\Model\ScopeInterface;

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
    protected $_subcriberCollectionFactory;

    /**
     * @var int
     */
    private $limit;

    /**
     * @param Data $helper
     * @param CollectionFactory $subcriberCollectionFactory
     */
    public function __construct(
        Data $helper,
        CollectionFactory $subcriberCollectionFactory
    ) {
        $this->helper   = $helper;
        $this->_subcriberCollectionFactory = $subcriberCollectionFactory;
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
        $subscriberCollection = $this->_subcriberCollectionFactory->create();
        if ($scope == ScopeInterface::SCOPE_STORES) {
            $subscriberCollection = $subscriberCollection->addStoreFilter($scopeId);
        }

        if ($callFrom == 'sendMass') {
            $subscriberCollection = $this->_subcriberCollectionFactory->create();
            $subscriberCollection->addStoreFilter($storeId);
            if (!empty($subscriberCollection->getData())  && $page > 0){
                $subscriberCollection->addAttributeToSort('entity_id', 'asc')->setPageSize($this->limit)->setCurPage($page);
            }
        }
        return $subscriberCollection;
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

        if (!empty($subscriberCollection) && count($subscriberCollection)>0) {
            $i = 0;
            $mass_data = $this->getMassData($uuid);
            foreach ($subscriberCollection as $subscriber) {
                $subscriberInfo = $this->getSubscriberInfo($subscriber, $scopeId, $storeId, $callFrom);
                $mass_data['data'][0]['object'][$i] = $subscriberInfo;
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
