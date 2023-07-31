<?php
namespace Routee\WaymoreRoutee\Controller\Adminhtml\Logs;

use \Magento\Backend\App\Action;
use \Magento\Backend\App\Action\Context;
use \Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

use Magento\Framework\App\ResourceConnection;
use Routee\WaymoreRoutee\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;

class ExportLogs extends Action
{
    /**
     * @var WriterInterface
     */
    protected $_saveConfig;

    /**
     * @var Json
     */
    protected $resultFactory;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Pool
     */
    protected $cacheFrontendPool;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param Context $context
     * @param WriterInterface $configWriter
     * @param Json $response
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     * @param ResourceConnection $resourceConnection
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context            $context,
        WriterInterface    $configWriter,
        Json               $response,
        TypeListInterface  $cacheTypeList,
        Pool               $cacheFrontendPool,
        ResourceConnection $resourceConnection,
        Data $helper,
        StoreManagerInterface $storeManager
    )
    {
        $this->_saveConfig = $configWriter;
        $this->resultFactory = $response;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->resourceConnection = $resourceConnection;
        $this->helper = $helper;
        $this->_storeManager = $storeManager;
        $this->limit = $this->helper->getRPRLimit();
        parent::__construct($context);
    }

    /**
     * @return string
     */
    public function execute()
    {
        if ($this->getRequest()->isAjax()) {
            $GET = $this->getRequest()->getParams();
            $result = [];
            switch ($GET['method']){
                case 'csv':
                    $result['data'] = $this->handleLogsCsv($GET);
					$result['success'] = 'yes';
                    break;

                case 'api':
                    $result = $this->handleLogsApi($GET);
                    break;
            }

            if ($result) {
                $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $response->setData($result);
                return $response;
            }
        }
        return '';
    }

    /**
     * @return array
     */
    public function handleLogsCsv()
    {
        $connection = $this->resourceConnection->getConnection();
        // get table name
        $table = $this->resourceConnection->getTableName('store_events_logs');
        $query = "SELECT * FROM $table WHERE is_exported='0'";
        return $connection->fetchAll($query);
    }

    /**
     * @return int[]
     */
    public function handleLogsApi()
    {
        $apiUrl = $this->helper->getApiurl('logs').'?multi=1';
        $logs = $this->handleLogsCsv();
        $result["reload"] = 0;
        $responseArr = $ids = $postArr = [];

        if (!empty($logs) && count($logs)>0) {
            $i = 0;

            foreach ($logs as $key => $log) {
                $ids[] = $log['id'];
                $postArr[] =  array(
                    'siteUrl' => $log['store_url'],
                    'uuid' => $this->helper->getUuid(),
                    'event_name' => $this->eventName($log['event_type']),
                    'log_type' => $log['log_type'] == 1 ? 'success' : 'failure',
                    'log_data' => $log['log_data'],
                    'created_at' => $log['created_at'],
                    'platform' => "Magento2"
                );
				$i++;
            }

            $responseArr = $this->helper->curl($apiUrl, $postArr, 'masslogs', 'yes');
        }
		$result = ['reload' => 1];
        $this->routeeUpdateLogs($responseArr['code'] ?? '', $ids);
        return $result;
    }

    /**
     * @param $eventType
     * @return string
     */
    public function eventName($eventType) {
        $name = '';

        switch ($eventType) {
            case 1:
                $name = "auth";
                break;
            case 3:
                $name = "massdata";
                break;
            case 2:
                $name = "events";
                break;
        }

        return $name;
    }

    /**
     * @param $code
     * @param $ids
     * @return void
     */
    public function routeeUpdateLogs($code, $ids) {
        if ($code == 200) {
            $connection = $this->resourceConnection->getConnection();
            // get table name
            $logs_table = $this->resourceConnection->getTableName('store_events_logs');
            $query = "UPDATE $logs_table SET is_exported=1 WHERE is_exported=0 AND id IN (".implode(',',$ids).")";
            return $connection->query($query);
        }
    }
}
