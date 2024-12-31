<?php
namespace Routee\WaymoreRoutee\Model\Api;

use Psr\Log\LoggerInterface;
use Magento\Config\Model\ResourceModel\Config;
use Routee\WaymoreRoutee\Api\PostManagementInterface;
use Magento\Framework\Webapi\Rest\Request;

/**
 *
 */
class PostManagement implements PostManagementInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Config
     */
    private $resourceConfig;

    /**
     * @param LoggerInterface $logger
     * @param Config $resourceConfig
     * @param Request $request
     */
    public function __construct(
        LoggerInterface $logger,
        Config $resourceConfig,
        Request $request
    ) {
        $this->logger = $logger;
        $this->resourceConfig = $resourceConfig;
        $this->request = $request;
    }

    /**
     * @return false|string
     */
    public function processData()
    {
        $post_values =  $this->request->getBodyParams();

        $response = ['success' => false];
        try {
            if (isset($post_values['data']) && $post_values['success'] == 1) {
                foreach ($post_values['data'] as $url) {
                    $this->resourceConfig->saveConfig('waymoreroutee/url/'.$url['type'], $url['url']);
                }
            }
            $response = ['success' => true, 'message' => $post_values];
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            $this->logger->info($e->getMessage());
        }
        return json_encode($response);
    }
}
