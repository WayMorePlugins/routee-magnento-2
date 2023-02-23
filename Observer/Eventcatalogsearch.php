<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session;
use Magento\Search\Model\QueryFactory;

class Eventcatalogsearch implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var Session
     */
    protected $customerSession;

    /**
     * @var QueryFactory
     */
    protected $_queryFactory;
    /**
     * @param Data $helper
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Session $customerSession
     * @param QueryFactory $queryFactory
     */
    public function __construct(
        Data $helper,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Session $customerSession,
        QueryFactory $queryFactory
    ) {
        $this->helper           = $helper;
        $this->scopeConfig      = $scopeConfig;
        $this->_storeManager    = $storeManager;
        $this->_customerSession = $customerSession;
        $this->_queryFactory    = $queryFactory;
    }

    /**
     * Observer execution
     *
     * @param EventObserver $observer
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(EventObserver $observer)
    {
        $isEnabled = $this->helper->getIsEnabled();
        if ($isEnabled) {
            $this->helper->eventExecutedLog('Search', 'events');

            $apiUrl     = $this->helper->getApiurl('events');
            $params = $this->getRequestParam();

            $this->helper->eventPayloadDataLog('Search', $params, 'events');
            $this->helper->curl($apiUrl, $params, 'events');
        }
    }

    /**
     * Get API request parameters
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getRequestParam()
    {
        $email = $phone = "";
        $searchTerm = $this->_queryFactory->get()->getQueryText();
        $storeId    = $this->_storeManager->getStore()->getId();
        $uuid       = $this->helper->getUuid($storeId);
        $customer = $this->_customerSession->getCustomer();
        $customerId = !is_null($customer->getId()) ? $customer->getId() : 0;
        if ($customerId) {
            $email = $customer->getEmail();
            $phone = $customer->getDefaultBillingAddress()->getTelephone();
        }
        $data['data']['customer_id'] = $customerId;
        $data['data']['email'] = $email;
        $data['data']['phone'] = $phone;
        $data['data']['search_string'] = $searchTerm;

        $this->helper->eventGrabDataLog('Search', $data, 'events');

        return [
            'uuid' => $uuid,
            'event' => 'Search',
            'data' => $data['data']
        ];
    }
}
