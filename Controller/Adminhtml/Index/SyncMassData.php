<?php

namespace Routee\WaymoreRoutee\Controller\Adminhtml\Index;

use \Magento\Backend\App\Action;
use \Magento\Backend\App\Action\Context;
use \Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\LocalizedException;
use Routee\WaymoreRoutee\Controller\Adminhtml\Data\CustomersMassData;
use Routee\WaymoreRoutee\Controller\Adminhtml\Data\ProductsMassData;
use Routee\WaymoreRoutee\Controller\Adminhtml\Data\OrdersMassData;
use Routee\WaymoreRoutee\Controller\Adminhtml\Data\WishlistsMassData;
use Routee\WaymoreRoutee\Controller\Adminhtml\Data\SubscribersMassData;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Routee\WaymoreRoutee\Helper\Data;

/**
 *
 */
class SyncMassData extends Action
{
    /**
     * @var CustomersMassData
     */
    protected $customersMassData;

    /**
     * @var ProductsMassData
     */
    protected $productsMassData;

    /**
     * @var OrdersMassData
     */
    protected $ordersMassData;

    /**
     * @var WishlistsMassData
     */
    protected $wishlistsMassData;

    /**
     * @var SubscribersMassData
     */
    protected $subscribersMassData;

    /**
     * @var WriterInterface
     */
    protected $_saveConfig;

    /**
     * @var Json
     */
    protected $resultFactory;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @param Context $context
     * @param WriterInterface $configWriter
     * @param CustomersMassData $customersMassData
     * @param ProductsMassData $productsMassData
     * @param OrdersMassData $ordersMassData
     * @param WishlistsMassData $wishlistsMassData
     * @param SubscribersMassData $subscribersMassData
     * @param Json $response
     * @param Data $helper
     */
    public function __construct(
        Context             $context,
        WriterInterface     $configWriter,
        CustomersMassData   $customersMassData,
        ProductsMassData    $productsMassData,
        OrdersMassData      $ordersMassData,
        WishlistsMassData   $wishlistsMassData,
        SubscribersMassData $subscribersMassData,
        Json                $response,
        Data                $helper
    ) {
        $this->productsMassData    = $productsMassData;
        $this->customersMassData   = $customersMassData;
        $this->ordersMassData      = $ordersMassData;
        $this->wishlistsMassData   = $wishlistsMassData;
        $this->subscribersMassData = $subscribersMassData;
        $this->_saveConfig   = $configWriter;
        $this->resultFactory = $response;
        $this->helper = $helper;
        parent::__construct($context);
    }

    /**
     * Is allowed
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return true;
    }

    /**
     * Execute mass data sync with routee on button click
     *
     * @throws LocalizedException
     */
    public function execute()
    {
        if ($this->getRequest()->isAjax()) {
            $params = $this->getRequest()->getParams();

            $result = [];
            switch ($params['action']) {
                case 'product_data':
                    $result = $this->handleProductExport($params);
                    break;

                case 'customer_data':
                    $result = $this->handleCustomerExport($params);
                    break;

                case 'order_data':
                    $result = $this->handleOrderExport($params);
                    break;

                case 'subscriber_data':
                    $result = $this->handleSubscriberExport($params);
                    break;

                case 'wishlist_data':
                    $result = $this->handleWishlistExport($params);
                    break;
            }

            if ($result) {
                $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $response->setData($result);
                $this->helper->clearCache();
                return $response;
            }
        }
        return '';
    }

    /**
     * @param  $params
     * @return array
     * @throws LocalizedException
     */
    private function handleProductExport($params)
    {
        $result = $this->productsMassData->syncProductsData($params);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->saveConfig('productmass', 1);
        }
        return $result;
    }

    /**
     * @param  $params
     * @return array
     * @throws LocalizedException
     */
    private function handleCustomerExport($params)
    {
        $result = $this->customersMassData->syncCustomersData($params);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->saveConfig('customermass', 1);
        }
        return $result;
    }

    /**
     * @param  $params
     * @return array
     */
    private function handleOrderExport($params)
    {
        $result = $this->ordersMassData->syncOrdersData($params);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->saveConfig('ordermass', 1, 'default', 0);
        }
        return $result;
    }

    /**
     * @param  $params
     * @return array
     */
    private function handleSubscriberExport($params)
    {
        $result = $this->subscribersMassData->syncSubscribersData($params);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->saveConfig('subscribermass', 1);
        }
        return $result;
    }

    /**
     * @param  $params
     * @return array
     */
    private function handleWishlistExport($params)
    {
        $result = $this->wishlistsMassData->syncWishlistsData($params);
        if (isset($result['reload']) && $result['reload'] == 1) {
            $this->saveConfig('wishlistmass', 1);
        }
        return $result;
    }

    /**
     * @param $var
     * @param $value
     * @return void
     */
    private function saveConfig($var, $value)
    {
        $this->_saveConfig->save("waymoreroutee/general/" . $var, $value, 'default', 0);
    }
}
