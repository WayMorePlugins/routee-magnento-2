<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\Module\ResourceInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;

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
     * @param WriterInterface $configWriter
     * @param RequestInterface $request
     * @param Data $helper
     * @param ResourceInterface $moduleResource
     * @param StoreManagerInterface $storeManager
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        WriterInterface $configWriter,
        RequestInterface $request,
        Data $helper,
        ResourceInterface $moduleResource,
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata
    ) {
        $this->configWriter       = $configWriter;
        $this->_request           = $request;
        $this->helper             = $helper;
        $this->moduleResource     = $moduleResource;
        $this->_storeManager      = $storeManager;
        $this->_productMetadata   = $productMetadata;
    }

    /**
     * Configuration event execution
     *
     * @param EventObserver $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function execute(EventObserver $observer)
    {
        $this->storeId   = $this->_request->getParam('store', 0);
        $this->websiteId = $this->_request->getParam('website', 0);
        $this->setScope();
        $isEnabled       = $this->helper->getIsEnabled($this->storeId);

        if ($isEnabled) {
            $postedData   = $this->_request->getPost()->get('groups');
            $postedDataFields = $postedData['general']['fields'];
            $postedDataUser = trim($postedDataFields['username']["value"]);
            $postedDataPass = trim($postedDataFields['password']["value"]);

            $uuld = $this->helper->getUuid($this->storeId);
            $usernameMain = trim($this->helper->getUsername($this->storeId));
            $passwordMain = trim($this->helper->getPassword($this->storeId));
            if ($uuld == '' || $postedDataUser != $usernameMain || $passwordMain != $postedDataPass) {
                $apiUrl = $this->helper->getApiurl('auth');
                $params = $this->getRequestParam($postedDataFields, $usernameMain, $passwordMain);

                if ($params["username"] != '' && $params["password"] != '') {
                    $responseArr = $this->helper->curl($apiUrl, $params);
                    $rsMsg = 'You are unauthorized. Invalid username or password';
                    if (isset($responseArr['message']) && $responseArr['message'] == $rsMsg) {
                        throw new \Magento\Framework\Exception\CouldNotDeleteException(__($responseArr['message']));
                    } else {
                        $newUuid = $responseArr['uuid'] ?? $uuld;
                    }
                    
                    $this->configWriter->save('waymoreroutee/general/uuid', $newUuid, $this->scope, $this->scopeId);
                }
            }
        } else {
            $this->configWriter->save('waymoreroutee/general/uuid', '', $this->scope, $this->scopeId);
        }
    }

    /**
     * Get API Request parameters
     *
     * @param array $postedDataFields
     * @param string $usernameMain
     * @param string $passwordMain
     * @return array
     * @throws NoSuchEntityException
     */
    public function getRequestParam($postedDataFields, $usernameMain, $passwordMain)
    {
        $enable     = $postedDataFields['enable'];
        $username   = $postedDataFields['username'];
        $password   = $postedDataFields['password'];
        $webVersion = $this->_productMetadata->getVersion();
        $pluginVersion = $this->moduleResource->getDbVersion('Routee_WaymoreRoutee');
        $domain = $this->_storeManager->getStore()->getBaseUrl();

        return [
            "username"          => !empty($username["value"])?trim($username["value"]):$usernameMain,
            "password"          => !empty($password["value"])?trim($password["value"]):$passwordMain,
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
}
