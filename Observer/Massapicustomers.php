<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Store\Model\ScopeInterface;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Event\Manager;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;

class Massapicustomers implements ObserverInterface
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
     * @var Manager
     */
    protected $_eventManager;

    /**
     * @var CollectionFactory
     */
    protected $_customerFactory;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param Manager $eventManager
     * @param CustomerFactory $customerFactory
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager,
        Manager $eventManager,
        CollectionFactory $customerFactory
    ) {
        $this->helper           = $helper;
        $this->_storeManager    = $storeManager;
        $this->_eventManager    = $eventManager;
        $this->_customerFactory = $customerFactory;
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
            $uuid       = $observer->getData('uuid');
            $scopeId    = $observer->getData('scopeId');
            $scope      = $observer->getData('scope');
            $websiteId  = $this->setWebsiteId($scopeId, $scope);
            $this->massApiCutomerAction('eventMass', $uuid, $websiteId, $scopeId, $scope);
        }
    }

    /**
     * Set Website Id
     *
     * @param int $scopeId
     * @param object $scope
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setWebsiteId($scopeId, $scope)
    {
        $websiteId  = $scopeId;
        if ($scope == ScopeInterface::SCOPE_STORES) {
            $websiteId = $this->_storeManager->getStore($scopeId)->getWebsiteId();
        } elseif ($scope == ScopeInterface::SCOPE_WEBSITES) {
            $websiteId = $scopeId;
        }
        return $websiteId;
    }

    /**
     * Get Customer Collection
     *
     * @param int $websiteId
     * @return \Magento\Customer\Model\ResourceModel\Customer\Collection
     */
    public function getCustomerCollection($websiteId)
    {
        $customerCollection = $this->_customerFactory->create();
        if ($websiteId > 0) {
            $customerCollection = $customerCollection
                ->addAttributeToSelect("*")
                ->addAttributeToFilter("website_id", ["eq" => $websiteId]);
        }
        return $customerCollection;
    }

    /**
     * Get API Mass data
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
                    'name' => 'Customers',
                    'description' => 'Shop Customers'
                ]
            ]
        ];
    }

    /**
     * Get Customre Information
     *
     * @param object $customer
     * @return array
     */
    public function getCustomerInfo($customer)
    {
        if ($customer->getDefaultBillingAddress()) {
            $phone      = $customer->getDefaultBillingAddress()->getTelephone();
            $country    = $customer->getDefaultBillingAddress()->getCountryModel()->getName();
            $city       = $customer->getDefaultBillingAddress()->getCity();
            $address1   = $customer->getDefaultBillingAddress()->getStreet()[0];
            $postcode   = $customer->getDefaultBillingAddress()->getPostcode();
            $company    = $customer->getDefaultBillingAddress()->getCompany();
        } else {
            $phone = $country = $city = $address1 = $postcode = $company = '';
        }

        return [
            'customer'  => $this->getCustomerDetail($customer, $phone),
            'addresses' => [
                [
                    'country'   => $country,
                    'city'      => $city,
                    'address1'  => $address1,
                    'postcode'  => $postcode,
                    'company'   => $company,
                ]
            ]
        ];
    }

    /**
     * Get Customer Details
     *
     * @param object $customer
     * @param string $phone
     * @return array
     */
    public function getCustomerDetail($customer, $phone)
    {
        return [
            'id'        => $customer->getId(),
            'lastname'  => $customer->getLastname(),
            'firstname' => $customer->getFirstname(),
            'birthday'  => $customer->getDob(),
            'email'     => $customer->getEmail(),
            'phone'     => $phone
        ];
    }

    /**
     * Get Customer data
     *
     * @param object $requestData
     * @return string|void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCustomerData($requestData)
    {
        $websiteId = $this->_storeManager->getWebsite()->getId();
        return $this->massApiCutomerAction('sendMass', $requestData['uuid'], $websiteId, 0, 0);
    }

    /**
     * Customer Mass API Action
     *
     * @param string $callFrom
     * @param string $uuid
     * @param int $websiteId
     * @param int $scopeId
     * @param object $scope
     * @return string|void
     */
    public function massApiCutomerAction($callFrom, $uuid, $websiteId, $scopeId, $scope)
    {
        $apiUrl     = $this->helper->getApiurl('massData');
        $customerCollection = $this->getCustomerCollection($websiteId);

        if (!empty($customerCollection) && count($customerCollection) > 0) {
            $c = $i = 0;
            $total_users = count($customerCollection);
            $mass_data   = $this->getMassData($uuid);

            foreach ($customerCollection as $customer) {
                $customerInfo = $this->getCustomerInfo($customer);
                $mass_data['data'][0]['object'][$i] = $customerInfo;

                $i++;
                $c++;

                if ($i == 100 || $c == $total_users) {
                    $responseArr = $this->helper->curl($apiUrl, $mass_data);
                    //response will contain the output in form of JSON string

                    $i = 0;
                    $mass_data['data'][0]['object'] = [];

                    if ($c == $total_users) {
                        if (!empty($responseArr['message']) && $callFrom == 'eventMass') {
                            $dispatchArr = ['uuid' => $uuid, 'scopeId' => $scopeId, 'scope' => $scope];
                            $this->_eventManager->dispatch('waymoreroutee_massapicustomer_complete', $dispatchArr);
                        } else {
                            return "CustomerDone";
                        }
                    }
                }
            }
        } else {
            return "CustomerDone";
        }
    }
}
