<?php

namespace Routee\WaymoreRoutee\Helper;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Store\Model\StoreManagerInterface;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\User\Model\ResourceModel\User\CollectionFactory as UserCollectionFactory;

class RouteeUrls {
    /**
     * @var string
     */

    public $euUrl = 'https://eu.api.wm.amdtelecom.net/api/endpoints';

    /**
     * @var Curl
     */
    protected $_curl;

    /**
     * @var ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var UserContextInterface
     */
    protected $userContext;

    /**
     * @param Curl $curl
     * @param ModuleListInterface $_moduleList
     * @param ProductMetadataInterface $productMetadata
     * @param StoreManagerInterface $storeManager
     * @param Data $data
     * @param UserCollectionFactory $userCollectionFactory
     */
    public function __construct(
        Curl $curl,
        ModuleListInterface $_moduleList,
        ProductMetadataInterface $productMetadata,
        StoreManagerInterface $storeManager,
        Data $data,
        UserCollectionFactory $userCollectionFactory
    ) {
        $this->_curl = $curl;
        $this->_moduleList = $_moduleList;
        $this->productMetadata = $productMetadata;
        $this->_storeManager = $storeManager;
        $this->helper = $data;
        $this->userCollectionFactory = $userCollectionFactory;
    }

    /**
     * @return mixed|string
     */
    public function fetchUrls()
    {
        try{
            $url = $this->euUrl.'/get';
            $payload = $this->commonEndpointPayload();
            return $this->sendData($url, $payload);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function commonEndpointPayload($uuid='')
    {
        return array(
            "store_uuid" => $uuid ?? $this->helper->getUuid(),
            "store_url" => $this->_storeManager->getStore()->getBaseUrl(),
            "platform" => "Magento2",
            "plugin_version" => $this->getExtensionVersion(),
            "platform_version" => $this->getMagentoVersion(),
            'user_id' => $this->getAdminUsers()['id'] ?? '',
            "email" => $this->getAdminUsers()['email'] ?? ''
        );
    }


    /**
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function saveCallbackUrl($uuid)
    {
        $url = $this->euUrl.'/save_callback';
        $payload = $this->commonEndpointPayload($uuid);
        $payload['store_callback_url'] = $this->_storeManager->getStore()->getBaseUrl().'rest/V1/routee-waymoreroutee/urls';

        return $this->sendData($url, json_encode($payload));
    }

    /**
     * @return mixed
     */
    public function getExtensionVersion()
    {
        return $this->_moduleList
            ->getOne('Routee_WaymoreRoutee')['setup_version'];
    }

    /**
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();;
    }

    /**
     * @param $url
     * @param $data
     * @return mixed
     */
    public function sendData($url, $data)
    {
        $this->_curl->post($url, $data);
        $response = $this->_curl->getBody();
        $code = $this->_curl->getStatus();

        return json_decode($response, true);
    }

    /**
     * Get admin user info
     *
     * @return array|mixed
     */
    private function getAdminUsers()
    {
        $adminUsers = [];

        foreach ($this->userCollectionFactory->create() as $user) {
            $adminUsers[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail()
            ];
        }

        return $adminUsers[0] ?? [];
    }

    public function uninstallModuleCallback()
    {
        $payload = $this->commonEndpointPayload();
        $url = $this->euUrl . "/uninstall";
        $this->sendData($url, $payload);
        $this->uninstallModule();
    }

    public function uninstallModule()
    {
        $storeId = $this->_storeManager->getStore()->getId();
        $uuid = $this->helper->getUuid($storeId);
        $eventData = [
            "data" => [],
            "uuid" => $uuid,
            "event" => "Uninstall"
        ];

        $url = $this->helper->getApiurl('event');

        $this->_curl->addHeader("Content-Type", "application/json");
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->post($url, $eventData);
    }
}
