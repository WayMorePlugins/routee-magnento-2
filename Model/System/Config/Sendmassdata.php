<?php

namespace Routee\WaymoreRoutee\Model\System\Config;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\Value;

class Sendmassdata extends Value
{
    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager
    ) {
        $this->_storeManager = $storeManager;
    }

    /**
     * After load
     *
     * @return Sendmassdata|void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function _afterLoad()
    {
        $baseUrl = $this->_storeManager->getStore()->getBaseUrl();
        $this->setValue($baseUrl);
    }
}
