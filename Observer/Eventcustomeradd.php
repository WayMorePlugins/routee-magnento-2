<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;

class Eventcustomeradd implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Observer event execution
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        $isEnabled = $this->helper->getIsEnabled();
        if ($isEnabled) {
            $customer   = $observer->getEvent()->getCustomer();
            $storeId    = $customer->getStoreId();
            $apiUrl     = $this->helper->getApiurl('events');
            $customers  = $this->helper->getCustomerInfo($customer);
            $addresses  = $customer->getAddresses();

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

            $data   = $this->helper->getCustomerReqData('CustomerAdd', $customers, $addrs, $storeId);
            $params = $this->helper->getRequestParam('CustomerAdd', $data, $storeId);
            $this->helper->curl($apiUrl, $params);
        }
    }
}
