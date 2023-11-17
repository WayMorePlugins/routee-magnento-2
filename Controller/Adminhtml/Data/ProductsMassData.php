<?php
namespace Routee\WaymoreRoutee\Controller\Adminhtml\Data;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Option;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\ProductRepository;

/**
 * Mass Products export class
 */
class ProductsMassData
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
    protected $_stockItemRepository;

    /**
     * @var int
     */
    private $limit;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var ProductRepository
     */
    protected $_productRepository;

    /**
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param Option $productOptions
     * @param StockItemRepository $stockItemRepository
     * @param ResourceConnection $resourceConnection
     * @param ProductRepository $productRepository
     */
    public function __construct(
        Data $helper,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        Option $productOptions,
        StockItemRepository $stockItemRepository,
        ResourceConnection $resourceConnection,
        ProductRepository $productRepository
    ) {
        $this->helper               = $helper;
        $this->_storeManager        = $storeManager;
        $this->categoryRepository   = $categoryRepository;
        $this->productOptions       = $productOptions;
        $this->_stockItemRepository = $stockItemRepository;
        $this->resourceConnection   = $resourceConnection;
        $this->_productRepository   = $productRepository;
        $this->limit                = $this->helper->getRPRLimit();
    }

    /**
     * Get Product data
     *
     * @param array $requestData
     * @return array
     * @throws LocalizedException
     */
    public function syncProductsData($requestData)
    {
        $page = $requestData['cycle_count'];
        $uuid = $requestData['uuid'];
        $storeId = $requestData['store_id'];
        return $this->massApiProductAction($uuid, $storeId, $page);
    }

    /**
     * Product Mass API Action
     *
     * @param string $uuid
     * @param int $storeId
     * @param int $page
     * @return array
     * @throws NoSuchEntityException
     */
    public function massApiProductAction($uuid, $storeId, $page = 0)
    {
        $productCollection = $this->getProductCollection($page);
        $this->helper->eventGrabDataLog('MassProduct', count($productCollection), 'massdata');
        if (!empty($productCollection) && count($productCollection)>0) {
            $i = 0;
            $mass_data = $this->getMassProData($uuid);

            foreach ($productCollection as $productEntity) {
                $product = $this->getProductById($productEntity);
                $mass_data['data'][0]['object'][$i] = $this->getMassProInfo($product, $storeId);
                $i++;
            }

            $this->helper->eventPayloadDataLog('MassProduct', count($mass_data['data'][0]['object']), 'massdata');
            $apiUrl = $this->helper->getApiurl('data');
            $responseArr = $this->helper->curl($apiUrl, $mass_data, 'massdata');
            $result = ['reload' => 0];
            if (!empty($responseArr['message'])) {
                // TODO
                if ($i < 9) {
                    $result = ['reload' => 1];
                }
            }
        } else {
            $result = ['reload' => 1];
        }
        return $result;
    }

    /**
     * Get Product collection
     *
     * @param $page
     * @return array
     */
    public function getProductCollection($page)
    {
        $start = ($this->limit * $page) - $this->limit;
        $tableName = $this->resourceConnection->getTableName('catalog_product_entity');
        $select = "SELECT entity_id FROM $tableName ORDER BY entity_id ASC LIMIT $start, " . $this->limit;
        return $this->resourceConnection->getConnection()->fetchAll($select);
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
     * Get Mass product information
     *
     * @param $product
     * @param $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getMassProInfo($product, $storeId)
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        return [
            'product_id'        => $product->getId(),
            'name'              => $product->getName(),
            'description'       => $product->getDescription(),
            'short_description' => $product->getShortDescription(),
            'stock_quantity'    => $this->getStockQuantity($product->getId()),
            'price'             => number_format($product->getPrice(), 2),
            'discount'          => $this->getProductDiscount($product),
            'image_link'        => $baseUrl.'catalog/product'.$product->getImage(),
            'product_link'      => $product->getProductUrl(),
            'categories'        => $this->getProductCategories($product, $storeId),
            'product_options'   => $this->productOptions->getProductOptionCollection($product)
        ];
    }

    /**
     * @param $productId
     * @return int
     */
    public function getStockQuantity($productId)
    {
        try {
            $stockItem = $this->_stockItemRepository->get($productId);
            $qty = $stockItem->getQty();
        } catch (\Exception $exception) {
            $qty = 0;
        }
        return intval($qty);
    }

    /**
     * @param $product
     * @param $storeId
     * @return array
     * @throws NoSuchEntityException
     */
    private function getProductCategories($product, $storeId)
    {
        $cats = [];
        $categories = $product->getCategoryIds();
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $categoryInstance = $this->categoryRepository->get($category, $storeId);
                $cats[] = $categoryInstance->getName();
            }
        }
        return $cats;
    }

    /**
     * Get Product discount
     *
     * @param object $product
     * @return array
     */
    public function getProductDiscount($product)
    {
        $specialPrice = $product->getSpecialPrice();
        $discount = [];
        if ($specialPrice > 0) {
            $discount = [
                'price'      => $specialPrice,
                'date_start' => $product->getSpecialFromDate(),
                'date_end'   => $product->getSpecialToDate(),
                'quantity'   => null
            ];
        }

        return $discount;
    }

    /**
     * @param $productEntity
     * @return ProductInterface|mixed|null
     * @throws NoSuchEntityException
     */
    public function getProductById($productEntity)
    {
        return $this->_productRepository->getById($productEntity['entity_id']);
    }
}
