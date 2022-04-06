<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Store\Model\ScopeInterface;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Event\Manager;
use Magento\Catalog\Model\Product\Option;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Framework\UrlInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class Massapiproducts implements ObserverInterface
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
    protected $_productCollectionFactory;

    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var Option
     */
    protected $productOptions;

    /**
     * @var StockItemRepository
     */
    protected $StockItemRepository;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param Manager $eventManager
     * @param CollectionFactory $productCollectionFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Option $productOptions
     * @param StockItemRepository $stockItemRepository
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager,
        Manager $eventManager,
        CollectionFactory $productCollectionFactory,
        CategoryRepositoryInterface $categoryRepository,
        Option $productOptions,
        StockItemRepository $stockItemRepository
    ) {
        $this->helper                       = $helper;
        $this->_storeManager                = $storeManager;
        $this->_eventManager                = $eventManager;
        $this->_productCollectionFactory    = $productCollectionFactory;
        $this->categoryRepository           = $categoryRepository;
        $this->productOptions               = $productOptions;
        $this->_stockItemRepository         = $stockItemRepository;
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
            $storeId    = $observer->getData('storeId');
            $websiteId  = $this->setWebsiteId($scopeId, $scope);
            $this->massApiProductAction('eventMass', $uuid, $websiteId, $scopeId, $scope, $storeId);
        }
    }

    /**
     * Set website id
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
     * Get Product collection
     *
     * @param int $websiteId
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getProductCollection($websiteId)
    {
        $productCollection = $this->_productCollectionFactory->create();

        if ($websiteId > 0) {
            $productCollection = $productCollection
                ->addAttributeToSelect("*")
                ->addWebsiteFilter($websiteId);
        }
        return $productCollection;
    }

    /**
     * Get Mass product data
     *
     * @param string $uuid
     * @return array
     */
    public function getMassProData($uuid)
    {
        return [
            'uuid' => $uuid,
            'data' => [
                [
                    'name' => 'Products',
                    'description' => 'Shop Products'
                ]
            ]
        ];
    }

    /**
     * Get Product discount
     *
     * @param object $product
     * @return array
     */
    public function getProductDiscount($product)
    {
        $specialprice = $product->getSpecialPrice();
        $discount = [];
        if ($specialprice > 0) {
            $discount = [
                'price'      => $specialprice,
                'date_start' => $product->getSpecialFromDate(),
                'date_end'   => $product->getSpecialToDate(),
                'quantity'   => null
            ];
        }

        return $discount;
    }

    /**
     * Get Mass product information
     *
     * @param object $product
     * @param array $discount
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getMassProInfo($product, $discount)
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $stockItem = $this->_stockItemRepository->get($product->getId());
        return [
            'product_id'        => $product->getId(),
            'name'              => $product->getName(),
            'description'       => $product->getDescription(),
            'short_description' => $product->getShortDescription(),
            'stock_quantity'    => $stockItem->getQty(),
            'price'             => $product->getPrice(),
            'discount'          => $discount,
            'image_link'        => $baseUrl.'catalog/product'.$product->getImage(),
            'product_link'      => $product->getProductUrl(),
        ];
    }

    /**
     * Get Product data
     *
     * @param object $requestData
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getProductData($requestData)
    {
        $websiteId = $this->_storeManager->getWebsite()->getId();
        $uuid = $requestData['uuid'];
        $storeId = $requestData['store_id'];

        return $this->massApiProductAction('sendMass', $uuid, $websiteId, 0, 0, $storeId);
    }

    /**
     * Product Mass API Action
     *
     * @param string $callFrom
     * @param string $uuid
     * @param int $websiteId
     * @param int $scopeId
     * @param int $scope
     * @param int $storeId
     * @return string|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function massApiProductAction($callFrom, $uuid, $websiteId, $scopeId, $scope, $storeId)
    {
        $apiUrl     = $this->helper->getApiurl('massData');
        $productCollection = $this->getProductCollection($websiteId);
        if (!empty($productCollection) && count($productCollection)>0) {
            $p = $i = 0;
            $total_products = count($productCollection);
            $mass_data = $this->getMassProData($uuid);

            foreach ($productCollection as $product) {
                $discount = $this->getProductDiscount($product);
                $mass_data['data'][0]['object'][$i] = $this->getMassProInfo($product, $discount);

                $categories = $product->getCategoryIds(); /*will return category ids array*/
                if (!empty($categories)) {
                    foreach ($categories as $category) {
                        $categoryInstance = $this->categoryRepository->get($category, $storeId);
                        $mass_data['data'][0]['object'][$i]['categories'][] = $categoryInstance->getName();
                    }
                }
                $proCollection = $this->productOptions->getProductOptionCollection($product);
                $mass_data['data'][0]['object'][$i]['product_options'] = $proCollection;
                $i++;
                $p++;

                if ($i == 100 || $p == $total_products) {
                    $responseArr = $this->helper->curl($apiUrl, $mass_data);
                    //response will contain the output in form of JSON string

                    $i = 0;
                    $mass_data['data'][0]['object'] = [];

                    if ($p == $total_products) {
                        if (!empty($responseArr['message']) && $callFrom == 'eventMass') {
                            $dispatchArr = ['uuid' => $uuid, 'scopeId' => $scopeId, 'scope' => $scope];
                            $this->_eventManager->dispatch('waymoreroutee_massapiproduct_complete', $dispatchArr);
                        } else {
                            return "ProductDone";
                        }
                    }
                }
            }
        } else {
            return "ProductDone";
        }
    }
}
