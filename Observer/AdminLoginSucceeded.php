<?php
namespace Routee\WaymoreRoutee\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\App\ResourceConnection;

class AdminLoginSucceeded implements ObserverInterface
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection
    )
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        $connection = $this->resourceConnection->getConnection();
        // get table name
        $logs_table = $this->resourceConnection->getTableName('store_events_logs');
        $query = "DELETE FROM $logs_table WHERE datediff(now(), $logs_table.created_at) > 7";
        $connection->query($query);
    }
}
