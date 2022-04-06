<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Customer\Model\Session;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Customer\Model\ResourceModel\Customer;

class Eventcustomerupdate implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Session
     */
    protected $_customerSession;

    /**
     * @var Customer
     */
    protected $customerResource;

    /**
     * @param Data $helper
     * @param Session $customerSession
     * @param Customer $customerResource
     */
    public function __construct(
        Data $helper,
        Session $customerSession,
        Customer $customerResource
    ) {
        $this->helper = $helper;
        $this->_customerSession = $customerSession;
        $this->customerResource = $customerResource;
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
            $customer       = $this->_customerSession->getCustomer()->getId();
            $storeId        = $observer->getEvent()->getStoreId();
            $apiUrl         = $this->helper->getApiurl('events');
            $customers      = $this->helper->getCustomerInfo($this->_customerSession->getCustomer());

            $addresses      = $this->_customerSession->getCustomer()->getAddresses();
            $addrs = [];
            if (empty($addresses)) {
                $customers['phone'] = "";
                $addrs[] = $this->helper->getBlankAddress();
            } else {
                foreach ($addresses as $address) {
                    $customers['phone'] = $address->getTelephone();
                    $addrs[] = $this->helper->getCustomerAvailableAddress($address);
                }
            }

            $data   = $this->helper->getCustomerReqData('CustomerUpdate', $customers, $addrs, $storeId);
            $params = $this->helper->getRequestParam('CustomerUpdate', $data, $storeId);
            $this->helper->curl($apiUrl, $params);
        }
    }
}
