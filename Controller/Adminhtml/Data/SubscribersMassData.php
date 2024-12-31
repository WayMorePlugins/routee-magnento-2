<?php
namespace Routee\WaymoreRoutee\Controller\Adminhtml\Data;

use Routee\WaymoreRoutee\Helper\Data;
use Magento\Framework\App\ResourceConnection;
use Magento\Newsletter\Model\Subscriber;

/**
 * Mass data export class for Newsletter subscribers data
 */
class SubscribersMassData
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var int
     */
    private $limit;
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;
    /**
     * @var Subscriber
     */
    private $subscriber;

    /**
     * @param Data $helper
     * @param Subscriber $subscriber
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Data $helper,
        Subscriber $subscriber,
        ResourceConnection $resourceConnection
    ) {
        $this->helper   = $helper;
        $this->subscriber = $subscriber;
        $this->resourceConnection = $resourceConnection;
        $this->limit = $this->helper->getRPRLimit();
    }

    /**
     * Get Subscriber data
     *
     * @param object $requestData
     * @return array
     */
    public function syncSubscribersData($requestData)
    {
        $uuid = $requestData['uuid'];
        $storeId = $requestData['store_id'];
        $page = $requestData['cycle_count'];
        return $this->massApiSubscriberAction($uuid, $storeId, $page);
    }

    /**
     * Subscriber Mass API action
     *
     * @param string $uuid
     * @param int $storeId
     * @param int $page
     * @return array
     */
    public function massApiSubscriberAction($uuid, $storeId, $page = 0)
    {
        $subscriberCollection = $this->getSubscriberCollection($page);
        $this->helper->eventGrabDataLog('MassSubscriber', count($subscriberCollection), 'massdata');

        if (!empty($subscriberCollection) && count($subscriberCollection)>0) {
            $i = 0;
            $mass_data = $this->getMassData($uuid);
            foreach ($subscriberCollection as $subscriber) {
                $mass_data['data'][0]['object'][$i] = $this->getSubscriberInfo($subscriber, $storeId);
                $i++;
            }

            $this->helper->eventPayloadDataLog('MassSubscriber', count($mass_data['data'][0]['object']), 'massdata');
            $apiUrl = $this->helper->getApiurl('data');
            $responseArr = $this->helper->curl($apiUrl, $mass_data, 'massData');

            $result = ['reload' => 0];
            if (!empty($responseArr['message'])) {
                if ($i < $this->limit) {
                    $result = ['reload' => 1];
                }
            }
        } else {
            $result = ['reload' => 1];
        }
        return $result;
    }

    /**
     * Get Subscriber Collection
     *
     * @param $page
     * @return array
     */
    public function getSubscriberCollection($page)
    {
        $start = ($this->limit * $page) - $this->limit;

        $tableName = $this->resourceConnection->getTableName('newsletter_subscriber');
        $select = "SELECT customer_id FROM $tableName ORDER BY subscriber_id ASC LIMIT $start, " . $this->limit;
        return $this->resourceConnection->getConnection()->fetchAll($select);
    }

    /**
     * Get Mass data
     *
     * @param string $uuid
     * @return array
     */
    public function getMassData($uuid)
    {
        return [
            'uuid' => $uuid,
            'data' => [
                [
                    'name' => 'Subscribers',
                    'description' => 'Shop Subscribers'
                ]
            ]
        ];
    }

    /**
     * Get Subscriber Information
     *
     * @param array $subscriber
     * @param int $scopeId
     * @param int $storeId
     * @param string $callFrom
     * @return array[]
     */
    public function getSubscriberInfo($subscriber, $storeId)
    {
        $subscriber = $this->subscriber->loadByCustomerId((int)$subscriber['customer_id']);
        return [
            'subscriber' => [
                'id'                         => $subscriber->getSubscriberId(),
                'id_shop'                    => $storeId,
                'id_shop_group'              => $storeId,
                'email'                      => $subscriber->getSubscriberEmail(),
                'newsletter_date_add'        => $subscriber->getChangeStatusAt(),
                'ip_registration_newsletter' => 'N/A',
                'http_referer'               => 'N/A',
                'active'                     => $subscriber->getSubscriberStatus(),
            ]
        ];
    }
}
