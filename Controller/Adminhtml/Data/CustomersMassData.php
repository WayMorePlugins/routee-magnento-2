<?php
namespace Routee\WaymoreRoutee\Controller\Adminhtml\Data;

use Magento\Framework\Exception\LocalizedException;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Customer\Model\Customer;

/**
 * Mass data export class for customers data
 */
class CustomersMassData
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
     * @var int
     */
    private $limit;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param Customer $customer
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        Customer $customer
    ) {
        $this->helper              = $helper;
        $this->_storeManager       = $storeManager;
        $this->resourceConnection  = $resourceConnection;
        $this->customer            = $customer;
        $this->limit               = $this->helper->getRPRLimit();
    }



    /**
     * Get Customer data
     *
     * @param array $requestData
     * @return array
     * @throws LocalizedException
     */
    public function syncCustomersData($requestData)
    {
        $page = $requestData['cycle_count'];
        $uuid = $requestData['uuid'];
        $storeId = $requestData['store_id'];
        $websiteId = $this->_storeManager->getStore($storeId)->getWebsiteId();
        return $this->massApiCustomerAction($uuid, $websiteId, $page);
    }

    /**
     * Customer Mass API Action
     *
     * @param string $uuid
     * @param int $websiteId
     * @param int $page
     * @return array
     * @throws LocalizedException
     */
    public function massApiCustomerAction($uuid, $websiteId, $page = 0)
    {
        $customerCollection = $this->getCustomerCollection($page);
        $this->helper->eventGrabDataLog('MassCustomer', count($customerCollection), 'massdata');
        if (!empty($customerCollection) && count($customerCollection) > 0) {
            $i = 0;
            $mass_data   = $this->getMassData($uuid);

            foreach ($customerCollection as $customerEntity) {
                $customerInfo = $this->getCustomerInfo($customerEntity, $websiteId);
                $mass_data['data'][0]['object'][$i] = $customerInfo;
                $i++;
            }

            $this->helper->eventPayloadDataLog('MassCustomer', count($mass_data['data'][0]['object']), 'massdata');
            $apiUrl     = $this->helper->getApiurl('data');
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
     * Get Customer Collection
     *
     * @param $page
     * @return array
     */
    public function getCustomerCollection($page)
    {
        $start = ($this->limit * $page) - $this->limit;
        $tableName = $this->resourceConnection->getTableName('customer_entity');
        $select = "SELECT email FROM $tableName ORDER BY entity_id ASC LIMIT $start, " . $this->limit;
        return $this->resourceConnection->getConnection()->fetchAll($select);
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
     * Get Customer Information
     *
     * @param $customerEntity
     * @param $websiteId
     * @return array
     * @throws LocalizedException
     */
    public function getCustomerInfo($customerEntity, $websiteId)
    {
        $customer = $this->getCustomerObject($customerEntity, $websiteId);

        $phone = $country = $city = $address1 = $postcode = $company = '';
        if ($customer->getDefaultBillingAddress()) {
            $phone      = $customer->getDefaultBillingAddress()->getTelephone();
            $country    = $customer->getDefaultBillingAddress()->getCountryModel()->getName();
            $city       = $customer->getDefaultBillingAddress()->getCity();
            $address1   = $customer->getDefaultBillingAddress()->getStreet()[0];
            $postcode   = $customer->getDefaultBillingAddress()->getPostcode();
            $company    = $customer->getDefaultBillingAddress()->getCompany();
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
     * @param $customerEntity
     * @param $websiteId
     * @return Customer
     * @throws LocalizedException
     */
    public function getCustomerObject($customerEntity, $websiteId)
    {
        return $this->customer->setWebsiteId($websiteId)->loadByEmail($customerEntity['email']);
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
}
