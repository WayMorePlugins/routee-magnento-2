<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Newsletter\Model\ResourceModel\Subscriber\CollectionFactory;
use Magento\Store\Model\ScopeInterface;

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
     * @param Data $helper
     * @param CollectionFactory $subcriberCollectionFactory
     */
    public function __construct(
        Data $helper,
        CollectionFactory $subcriberCollectionFactory
    ) {
        $this->helper   = $helper;
        $this->_subcriberCollectionFactory = $subcriberCollectionFactory;
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
        return $this->massApiSubscriberAction($uuid, 'sendMass', $storeId, 0, 0);
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
    public function getSubscriberCollection($callFrom, $storeId, $scopeId, $scope)
    {
        $subscriberCollection = $this->_subcriberCollectionFactory->create();
        if ($scope == ScopeInterface::SCOPE_STORES) {
            $subscriberCollection = $subscriberCollection->addStoreFilter($scopeId);
        }

        if ($callFrom == 'sendMass') {
            $subscriberCollection = $this->_subcriberCollectionFactory->create();
            $subscriberCollection->addStoreFilter($storeId);
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
     * @return string|void
     */
    public function massApiSubscriberAction($uuid, $callFrom, $storeId, $scopeId, $scope)
    {
        $apiUrl     = $this->helper->getApiurl('massData');
        $subscriberCollection = $this->getSubscriberCollection($callFrom, $storeId, $scopeId, $scope);
        if (!empty($subscriberCollection) && count($subscriberCollection)>0) {
            $s = $i = 0;
            $total_subscribers = count($subscriberCollection);
            $mass_data = $this->getMassData($uuid);
            foreach ($subscriberCollection as $subscriber) {
                $subscriberInfo = $this->getSubscriberInfo($subscriber, $scopeId, $storeId, $callFrom);
                $mass_data['data'][0]['object'][$i] = $subscriberInfo;
                $i++;
                $s++;
                if ($i == 100 || $s == $total_subscribers) {
                    $responseArr = $this->helper->curl($apiUrl, $mass_data);
                    //response will contain the output in form of JSON string
                    $i = 0;
                    $mass_data['data'][0]['object'] = [];
                    if ($s == $total_subscribers) {
                        if (!empty($responseArr['message']) && $callFrom == 'eventMass') {
                            $dispatchArr = ['uuid' => $uuid, 'scopeId' => $scopeId, 'scope' => $scope];
                            $this->_eventManager->dispatch('waymoreroutee_massapisubscriber_complete', $dispatchArr);
                        } else {
                            return "SubscriberDone";
                        }
                    }
                }
            }
        } else {
            return "SubscriberDone";
        }
    }
}
