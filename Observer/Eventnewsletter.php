<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Customer\Model\Session;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;

class Eventnewsletter implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager,
        Session $customerSession
    ) {
        $this->helper = $helper;
        $this->_storeManager = $storeManager;
        $this->_customerSession = $customerSession;
    }

    /**
     * Observer Event execution
     *
     * @param EventObserver $observer
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(EventObserver $observer)
    {
        $isEnabled = $this->helper->getIsEnabled();
        if ($isEnabled) {
            $this->helper->eventExecutedLog('Newsletter', 'events');

            $storeId        = $this->_storeManager->getStore()->getId();
            $uuid           = $this->helper->getUuid($storeId);
            $customerId     = $this->_customerSession->getCustomer()->getId();
            $customerId     = $customerId < 1 ? 0 : $customerId;
            $isSubscribed   = $observer->getEvent()->getSubscriber()->getSubscriberStatus();

            $apiUrl         = $this->helper->getApiurl('event');
            $data           = $this->getNewsletterData($customerId, $uuid, $observer, $isSubscribed);
            $this->helper->eventGrabDataLog('Newsletter', $data, 'events');

            $params         = $this->helper->getRequestParam('Newsletter', $data, $storeId);
            $this->helper->eventPayloadDataLog('Newsletter', $params, 'events');

            $this->helper->curl($apiUrl, $params, 'events');
        }
    }

    /**
     * Get Newsletter Data
     *
     * @param int $customerId
     * @param string $uuid
     * @param object $observer
     * @param string $isSubscribed
     * @return array
     */
    public function getNewsletterData($customerId, $uuid, $observer, $isSubscribed)
    {
        return [
            'uuid'  => $uuid,
            'event' => 'Newsletter',
            'data'  => [
                'customer_id'   => $customerId,
                'email'         => $observer->getEvent()->getSubscriber()->getSubscriberEmail(),
                'subscription'  => ($isSubscribed == '1')?'true':'false'
            ]
        ];
    }
}
