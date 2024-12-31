<?php

namespace Routee\WaymoreRoutee\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Routee\WaymoreRoutee\Helper\Data;

class ExportLogsAPI extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Routee_WaymoreRoutee::system/config/exportLogsApi.phtml';

    /**
     * @var object
     */
    protected $helper;

    /**
     * @var int
     */
    protected $storeId = 0;

    /**
     * @param Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get html element
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * @return mixed
     * @throws LocalizedException
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()
            ->createBlock('Magento\Backend\Block\Widget\Button')
            ->setData(
                [
                    'id' => 'routee_log_export_api',
                    'class' => 'primary routee-log-export-api',
                    'label' => __('Send Logs')
                ]
            )
            ->setDataAttribute(
                [
                    'action' => 'export_log_api',
                    'url' => $this->getAjaxUrlApi()
                ]
            );
        return $button->toHtml();
    }

    /**
     * @return string
     */
    public function getAjaxUrlApi()
    {
        $uuid = $this->getUUID();
        return $this->getUrl('waymoreroutee/logs/exportlogs').'?store_id='.$this->storeId.'&uuid='.$uuid.'&method=api';
    }

    /**
     * @return string
     */
    public function getUUID()
    {
        $this->storeId = $this->_storeManager->getStore()->getId();
        return $this->helper->getUuid($this->storeId);
    }
}
