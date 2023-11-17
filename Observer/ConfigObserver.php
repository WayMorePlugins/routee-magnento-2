<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\Module\ResourceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Routee\WaymoreRoutee\Helper\RouteeUrls;

class ConfigObserver implements ObserverInterface
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var RequestInterface
     */
    protected $_request;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ResourceInterface
     */
    protected $moduleResource;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ProductMetadataInterface
     */
    protected $_productMetadata;

    /**
     * @var mixed
     */
    protected $storeId;

    /**
     * @var mixed
     */
    protected $websiteId;

    /**
     * @var int
     */
    protected $scopeId;

    /**
     * @var string
     */
    protected $scope;

    /**
     * @var string
     */
    protected $routeeUrls;

    /**
     * @var string
     */
    protected $authUrl = '';

    /**
     * @param WriterInterface $configWriter
     * @param RequestInterface $request
     * @param Data $helper
     * @param ResourceInterface $moduleResource
     * @param StoreManagerInterface $storeManager
     * @param ProductMetadataInterface $productMetadata
     * @param RouteeUrls $routeeUrls
     */
    public function __construct(
        WriterInterface $configWriter,
        RequestInterface $request,
        Data $helper,
        ResourceInterface $moduleResource,
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata,
        RouteeUrls $routeeUrls
    ) {
        $this->configWriter       = $configWriter;
        $this->_request           = $request;
        $this->helper             = $helper;
        $this->moduleResource     = $moduleResource;
        $this->_storeManager      = $storeManager;
        $this->_productMetadata   = $productMetadata;
        $this->routeeUrls   = $routeeUrls;
    }

    /**
     * Configuration event execution
     *
     * @param EventObserver $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function execute(EventObserver $observer)
    {
        $this->fetchEnvUrls();
        $this->helper->eventExecutedLog('Authentication', 'auth');
        $this->setScope();
        $isEnabled = $this->helper->getIsEnabled($this->storeId);
        $uuid = $this->helper->getUuid($this->storeId);
        if ($isEnabled) {
            $authInputData = $this->authInputData();
            $this->helper->eventGrabDataLog('Authentication', $authInputData, 'auth');
            if ($uuid == '' || !$authInputData['isUsernameSame'] || !$authInputData['isPassSame']) {
                $this->getAuthentication($authInputData);
            }
        } else {
            $this->saveDefaultValues();
            $error = $this->getErrorMsg($uuid);
            throw new LocalizedException(__($error));
        }
    }

    /**
     * @return void
     */
    public function fetchEnvUrls()
    {
        $checkUrl = $this->helper->getApiurl('auth');

        if (is_null($checkUrl)) {
            $this->routeeUrls->deleteConfig();
            //Fetch and save Routee URLs
            $urls = $this->routeeUrls->routeeSaveUrls();
            if (isset($urls['data']) && $urls['success'] == 1) {
                $key = array_search('auth', array_column($urls['data'], 'type'));
                $this->authUrl = $urls['data'][$key]['url'];
            }
        }
    }

    /**
     * @return void
     */
    private function saveDefaultValues()
    {
        $this->configWriter->save('waymoreroutee/general/uuid', '', $this->scope, $this->scopeId);
        $this->configWriter->save('waymoreroutee/general/username', '', $this->scope, $this->scopeId);
        $this->configWriter->save('waymoreroutee/general/password', '', $this->scope, $this->scopeId);
    }

    /**
     * Get API Request parameters
     *
     * @param $authInputData
     * @return array
     * @throws NoSuchEntityException
     */
    public function getRequestParam($authInputData)
    {
        $webVersion = $this->_productMetadata->getVersion();
        $pluginVersion = $this->moduleResource->getDbVersion('Routee_WaymoreRoutee');
        $domain = $this->_storeManager->getStore()->getBaseUrl();

        return [
            "username"          => !empty($authInputData['postedUsername']) ? trim($authInputData['postedUsername']) : $authInputData['dbUsername'],
            "password"          => !empty($authInputData['postedPass']) ? trim($authInputData['postedPass']) : $authInputData['dbPassword'],
            "source"            => 'Magento',
            "type"              => 'E-Commerce',
            "version"           => $webVersion,
            "plugin_version"    => $pluginVersion,
            "domain"            => $domain
        ];
    }

    /**
     * Set Scope variables
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function setScope()
    {
        $this->storeId   = $this->_request->getParam('store', 0);
        $this->websiteId = $this->_request->getParam('website', 0);

        if ($this->storeId > 0) {
            $this->scopeId = $this->_storeManager->getStore()->getId();
            $this->scope = \Magento\Store\Model\ScopeInterface::SCOPE_STORES;
        } elseif ($this->websiteId > 0) {
            $this->scopeId = $this->_storeManager->getWebsite()->getId();
            $this->scope = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES;
        } else {
            $this->scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $this->scopeId = 0;
        }
    }

    /**
     * @return array
     */
    public function authInputData()
    {
        $postedData   = $this->_request->getPost()->get('groups');
        $this->helper->eventGrabDataLog('Authentication', $postedData, 'auth');

        $postedDataFields = $postedData['general']['fields'];
        $postedUsername = trim($postedDataFields['username']["value"]);
        $postedPass = trim($postedDataFields['password']["value"]);
        $dbUsername = trim($this->helper->getUsername($this->storeId));
        $dbPassword = trim($this->helper->getPassword($this->storeId));

        return [
            'postedUsername' => $postedUsername,
            'postedPass' => $postedPass,
            'dbUsername' => $dbUsername,
            'dbPassword' => $dbPassword,
            'isUsernameSame' => $postedUsername == $dbUsername,
            'isPassSame' => $postedPass == $dbPassword
        ];
    }

    /**
     * @param $authInputData
     * @return void
     * @throws AuthorizationException
     * @throws NoSuchEntityException
     */
    public function getAuthentication($authInputData)
    {
        $apiUrl = empty($this->authUrl) ? $this->helper->getApiurl('auth') : $this->authUrl;
        $params = $this->getRequestParam($authInputData);

        if ($params["username"] != '' && $params["password"] != '') {

            $this->helper->eventPayloadDataLog('Authentication', $params, 'auth');

            $responseArr = $this->helper->curl($apiUrl, $params, 'auth');

            if (isset($responseArr['uuid'])) {
                $this->configWriter->save('waymoreroutee/general/enable', 1, $this->scope, $this->scopeId);
                $this->configWriter->save('waymoreroutee/general/uuid', $responseArr['uuid'], $this->scope, $this->scopeId);
                $this->configWriter->save('waymoreroutee/general/username', $params["username"], $this->scope, $this->scopeId);
                $this->configWriter->save('waymoreroutee/general/password', $params["password"], $this->scope, $this->scopeId);
            } else {
                $this->saveDefaultValues();
                throw new AuthorizationException(__($responseArr['message']));
            }
        }
    }

    /**
     * @param  $uuid
     * @return string
     */
    public function getErrorMsg($uuid)
    {
        if ($uuid) {
            $error = "Your module disabled successfully.";
        } else {
            $error = "You must enable the module to save the data";
        }
        return $error;
    }
}
