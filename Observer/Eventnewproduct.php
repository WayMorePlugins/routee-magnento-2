<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Exception\NoSuchEntityException;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\UrlInterface;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;

class Eventnewproduct implements ObserverInterface
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
    protected $stockItemRepository;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Option $productOptions
     * @param stockItemRepository $stockItemRepository
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        Option $productOptions,
        StockItemRepository $stockItemRepository
    ) {
        $this->helper               = $helper;
        $this->_storeManager        = $storeManager;
        $this->categoryRepository   = $categoryRepository;
        $this->productOptions       = $productOptions;
        $this->stockItemRepository = $stockItemRepository;
    }

    /**
     * Observer Event execution
     *
     * @param EventObserver $observer
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(EventObserver $observer)
    {
        $isEnabled = $this->helper->getIsEnabled();
        if ($isEnabled) {
            $product    = $observer->getEvent()->getProduct();
            $eventName = $product->isObjectNew() ? 'ProductAdd' : 'ProductUpdate';
            $this->helper->eventExecutedLog($eventName, 'events');

            $storeId    = $this->_storeManager->getStore()->getId();
            $uuid       = $this->helper->getUuid($storeId);
            $apiUrl     = $this->helper->getApiurl('event');
            $data       = $this->getProductData($product, $uuid, $storeId, $eventName);

            $this->helper->eventGrabDataLog($eventName, $data, 'events');

            $params     = $this->helper->getRequestParam($data['event'], $data, $storeId);

            $this->helper->eventPayloadDataLog($eventName, $params, 'events');
            $this->helper->curl($apiUrl, $params, 'events');
        }
    }

    /**
     * Get Product data
     *
     * @param object $product
     * @param string $uuid
     * @param int $storeId
     * @param string $eventName
     * @return array
     * @throws NoSuchEntityException
     */
    public function getProductData($product, $uuid, $storeId, $eventName)
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $stockItem = $this->stockItemRepository->get($product->getId());

        return [
            'uuid'      => $uuid,
            'event'     => $eventName,
            'data'      => [
                'product_id'        => $product->getId(),
                'name'              => $product->getName(),
                'description'       => $product->getDescription(),
                'short_description' => $product->getShortDescription(),
                'categories'        => $this->getProductCategories($product, $storeId),
                'stock_quantity'    => $stockItem->getQty(),
                'image_link'        => $baseUrl.'catalog/product'.$product->getImage(),
                'product_link'      => $product->getProductUrl(),
                'product_options'   => $this->productOptions->getProductOptionCollection($product)->getData(),
                'price'             => $product->getPrice(),
                'discount'          => $this->getProductDiscount($product)
            ]
        ];
    }

    /**
     * Get Product categories
     *
     * @param object $product
     * @param int $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getProductCategories($product, $storeId)
    {
        $categories = $product->getCategoryIds(); /*will return category ids array*/
        $catNames = [];
        foreach ($categories as $category) {
            $categoryInstance = $this->categoryRepository->get($category, $storeId);
            $catNames[] = $categoryInstance->getName();
        }
        return $catNames;
    }

    /**
     * Get Product Discount info
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
}
