<?php
namespace Routee\WaymoreRoutee\Helper;
 
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Directory\Model\CountryFactory;
use Magento\Store\Model\ScopeInterface;

/**
 * Helper class
 */
class Data extends AbstractHelper
{
    /**
     * @var HttpContext
     */
    private $httpContext;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Curl
     */
    protected $_curl;

    /**
     * @var CountryFactory
     */
    protected $_countryFactory;

    /**
     * Mass data export records per request limit
     */
    const RPR_LIMIT = 100;

    /**
     * @param Context $context
     * @param HttpContext $httpContext
     * @param ScopeConfigInterface $scopeConfig
     * @param Curl $curl
     * @param CountryFactory $countryFactory
     */
    public function __construct(
        Context $context,
        HttpContext $httpContext,
        ScopeConfigInterface $scopeConfig,
        Curl $curl,
        CountryFactory $countryFactory
    ) {
        parent::__construct($context);
        $this->httpContext = $httpContext;
        $this->scopeConfig = $scopeConfig;
        $this->_curl       = $curl;
        $this->_countryFactory = $countryFactory;
    }

    /**
     * Get API URL
     *
     * @param string $urlFor
     * @return string
     */
    public function getApiurl($urlFor)
    {
        $apiUrl = '';
        switch ($urlFor) {
            case "auth":
                $apiUrl = 'https://waymore.routee.net/api/authenticate';
                break;
            case "massData":
                $apiUrl = 'https://idata.routee.net/api/data';
                break;
            case "events":
                $apiUrl = 'https://idata.routee.net/api/event';
                break;
        }
        
        return $apiUrl;
    }

    /**
     * @return int
     */
    public function getRPRLimit()
    {
        return self::RPR_LIMIT;
    }

    /**
     * @param $path
     * @return mixed
     */
    public function getConfigValue($path)
    {
        return $this->scopeConfig->getValue($path);
    }

    /**
     * Get WayMOre UUID
     *
     * @param int|bool $storeId
     * @return mixed
     */
    public function getUuid($storeId = null)
    {
        $confUuidPath = 'waymoreroutee/general/uuid';
        if ($storeId > 0) {
            return $this->scopeConfig->getValue($confUuidPath, ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->getValue($confUuidPath);
        }
    }

    /**
     * Get Username
     *
     * @param int|bool $storeId
     * @return mixed
     */
    public function getUsername($storeId = null)
    {
        $confUserPath = 'waymoreroutee/general/username';
        if ($storeId > 0) {
            return $this->scopeConfig->getValue($confUserPath, ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->getValue($confUserPath);
        }
    }

    /**
     * Get Password
     *
     * @param int|bool $storeId
     * @return mixed
     */
    public function getPassword($storeId = null)
    {
        $confPassPath = 'waymoreroutee/general/password';
        if ($storeId > 0) {
            return $this->scopeConfig->getValue($confPassPath, ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->getValue($confPassPath);
        }
    }

    /**
     * Get If Module is enabled or not
     *
     * @param int|bool $storeId
     * @return mixed
     */
    public function getIsEnabled($storeId = null)
    {
        $confEnablePath = 'waymoreroutee/general/enable';
        if ($storeId > 0) {
            return $this->scopeConfig->getValue($confEnablePath, ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->getValue($confEnablePath);
        }
    }

    /**
     * Common Curl Execution
     *
     * @param string $apiUrl
     * @param array $params
     * @return mixed
     */
    public function curl($apiUrl, $params)
    {
        $this->_curl->post($apiUrl, json_encode($params));
        $this->_curl->addHeader("Content-Type", "application/json");
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        //response will contain the output in form of JSON string
        $response = $this->_curl->getBody();
        return json_decode($response, true);
    }

    /**
     * Get Customer Information
     *
     * @param object $customer
     * @return array
     */
    public function getCustomerInfo($customer)
    {
        return [
            'id'        => $customer->getId(),
            'lastname'  => $customer->getLastname(),
            'firstname' => $customer->getFirstname(),
            'birthday'  => $customer->getDob(),
            'email'     => $customer->getEmail()
        ];
    }

    /**
     * Get customer Address Blank array
     *
     * @return string[]
     */
    public function getBlankAddress()
    {
        return [
            'country'   => '',
            'city'      => '',
            'address1'  => '',
            'postcode'  => '',
            'company'   => ''
        ];
    }

    /**
     * Get Customer Address if available
     *
     * @param object $address
     * @return array
     */
    public function getCustomerAvailableAddress($address)
    {
        $countryCode = $address->getCountryId();
        $country = $this->_countryFactory->create()->loadByCode($countryCode);

        return [
            'country'   => $country,
            'city'      => $address->getCity(),
            'address1'  => $address->getStreet()[0],
            'postcode'  => $address->getPostcode(),
            'company'   => $address->getCompany()
        ];
    }

    /**
     * Get Customer API Request Data
     *
     * @param string $method
     * @param array $customers
     * @param array $addrs
     * @param int|bool $storeId
     * @return array
     */
    public function getCustomerReqData($method, $customers, $addrs, $storeId)
    {
        return [
            'uuid' => $this->getUuid($storeId),
            'event' => $method,
            'data' => [
                'customer' => $customers,
                'addresses' => $addrs
            ],
        ];
    }

    /**
     * Get Request parameters
     *
     * @param string $method
     * @param array $data
     * @param int|bool $storeId
     * @return array
     */
    public function getRequestParam($method, $data, $storeId)
    {
        return [
            'uuid' => $this->getUuid($storeId),
            'event' => $method,
            'data' => $data['data']
        ];
    }
}
