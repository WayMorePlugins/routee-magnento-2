<?php
namespace Routee\WaymoreRoutee\Helper;

use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Directory\Model\CountryFactory;
use Magento\Store\Model\ScopeInterface;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;

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
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Mass data export records per request limit
     */
    const RPR_LIMIT = 100;
    /**
     * @var TypeListInterface
     */
    private $_cacheTypeList;
    /**
     * @var Pool
     */
    private $_cacheFrontendPool;

    /**
     * @param Context $context
     * @param HttpContext $httpContext
     * @param ScopeConfigInterface $scopeConfig
     * @param Curl $curl
     * @param CountryFactory $countryFactory
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     */
    public function __construct(
        Context $context,
        HttpContext $httpContext,
        ScopeConfigInterface $scopeConfig,
        Curl $curl,
        CountryFactory $countryFactory,
        StoreManagerInterface $storeManager,
        ResourceConnection $resourceConnection,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        parent::__construct($context);
        $this->httpContext = $httpContext;
        $this->scopeConfig = $scopeConfig;
        $this->_curl       = $curl;
        $this->_countryFactory = $countryFactory;
        $this->_storeManager = $storeManager;
        $this->resourceConnection = $resourceConnection;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * Get API URL
     *
     * @param string $urlFor
     * @return string
     */
    public function getApiurl($urlFor)
    {
        return $this->getConfigValue('waymoreroutee/url/'.$urlFor);
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
     * @param $apiUrl
     * @param $params
     * @param $mode
     * @param $isLog
     * @return mixed
     */
    public function curl($apiUrl, $params, $mode, $isLog = '')
    {
        $event = $params['event'] ?? '';
        $data = json_encode($params);
        if ($mode == 'auth') {
            $data = htmlspecialchars_decode($data);
        }
        try {
            $this->_curl->addHeader("Content-Type", "application/json");
            $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->_curl->post($apiUrl, $data);
            $response = $this->_curl->getBody();

            $code = $this->_curl->getStatus();

            if ($isLog != 'yes') {
                $this->writeLog($mode, $event, '');
            } else {
                return ['response' => $response, 'code' => $code];
            }

            return json_decode($response, true);
        } catch (\Exception $e) {
            $this->writeLog($mode, $event, $e);
            return ['message' => $e->getMessage(), 'code' => $e->getCode()];
        }
    }

    /**
     * @param $mode
     * @param $event
     * @param $exception
     * @return void
     */
    private function writeLog($mode, $event, $exception)
    {
        $code = empty($exception) ? '200' : $exception->getCode();
        $msg = empty($exception) ? 'HTTP executed successfully.' : $exception->getMessage();
        $message = [
            'mode' => $mode,
            'event' => $event,
            'code' => $code,
            'exception_message' => $msg
        ];
        //Writing http call logs
        $this->logsInitated($mode, $code, $message);
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

    /**
     * @param $mode
     * @param $code
     * @param $logdata
     */
    public function logsInitated($mode, $code, $logdata)
    {
        $this->saveLogs($mode, $code, $logdata);
        $this->sendErrorLog($code, $logdata);
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     */
    public function saveLogs($mode, $code, $logdata)
    {
        $connection = $this->resourceConnection->getConnection();
        // get table name
        $table = $this->resourceConnection->getTableName('store_events_logs');
        $isExported = $this->getConfigValue('routee/log/exported') ?? 0;
        $logType = ($code == 200) ? 1 : 0;
        $storeUrl = $this->_storeManager->getStore()->getUrl();

        $data = json_encode($logdata);
        $time = gmdate('d-m-Y H:i:s');
        $query = "INSERT INTO ".$table." (store_url, log_type,event_type,log_data,is_exported,created_at) VALUES ('{$storeUrl}','{$logType}','{$this->eventType($mode)}','{$data}','{$isExported}','{$time}')";

        $connection->query($query);
    }

    /**
     * @return void
     */
    public function sendErrorLog($code, $logdata)
    {

        if ($code != 200) {
            $apiUrl = $this->getApiurl('logs');

            $postArr = [
                'siteUrl' => $this->_storeManager->getStore()->getUrl(),
                'uuid' => $this->getUuid(),
                'event_name' => !empty($logdata['event']) ? $logdata['event'] : $logdata['mode'],
                'log_type' => 'failure',
                'log_data' => $logdata,
                'created_at' => gmdate('d-m-Y H:i:s'),
                'platform' => "Magento2"
            ];

            $this->_curl->post($apiUrl, json_encode($postArr));
            $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $response = $this->_curl->getBody();
            $code = $this->_curl->getStatus();
        }
    }

    /**
     * @return int
     */
    public function eventType($mode)
    {
        $type = 0;

        switch ($mode) {
            case "auth":
                $type = 1;
                break;
            case "events":
                $type = 2;
                break;
            case "massdata":
                $type = 3;
                break;
        }

        return $type;
    }

    /**
     * @param  $func
     * @param  $method
     * @return void
     */
    public function eventExecutedLog($func, $method)
    {
        $payload = [
            'function' => $func,
            'method' => $method,
            'desc' => 'Hook function executed.'
        ];
        $this->saveLogs($method, 200, $payload);
    }

    /**
     * @param  $func
     * @param  $data
     * @param  $method
     * @return void
     */
    public function eventGrabDataLog($func, $data, $method)
    {
        $payload = [
            'function' => $func,
            'method' => $method,
            'postdata' => $data,
            'desc' => 'Event data grabbed.'
        ];
        $this->saveLogs($method, 200, $payload);
    }

    /**
     * @param  $func
     * @param  $data
     * @param  $method
     * @return void
     */
    public function eventPayloadDataLog($func, $data, $method)
    {
        $payload = [
            'function' => $func,
            'method' => $method,
            'api_payload' => $data,
            'desc' => 'Event API payload is prepared.'
        ];
        $this->saveLogs($method, 200, $payload);
    }

    /**
     * @return void
     */
    public function clearCache()
    {
        $types = ['config','layout','block_html','collections','reflection','db_ddl','eav','config_integration','config_integration_api','full_page','translate','config_webservice'];
        foreach ($types as $type) {
            $this->_cacheTypeList->cleanType($type);
        }
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
}
