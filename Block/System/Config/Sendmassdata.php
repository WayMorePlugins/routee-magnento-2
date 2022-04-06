<?php

namespace Routee\WaymoreRoutee\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Routee\WaymoreRoutee\Helper\Data;

class Sendmassdata extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Routee_WaymoreRoutee::system/config/sendmassdata.phtml';

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
     * Set template to itself
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(static::_template);
        }
        return $this;
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
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->storeId = $this->_storeManager->getStore()->getId();
        $uuid = $this->helper->getUuid($this->storeId);

        if ($uuid != '') {
            $onClickEvent = $this->getClickEvent($uuid);
        } else {
            $onClickEvent = 'javascript:alert("Please save config first!"); return false;';
        }

        $this->addData(
            [
                'id' => 'send_mass_data',
                'label' => __('Start Sync'),
                'onclick' => $onClickEvent
            ]
        );
        return $this->_toHtml();
    }

    /**
     * Generate Start Sync button html
     *
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getButtonUrl()
    {
        $this->storeId = $this->_storeManager->getStore()->getId();
        $uuid = $this->helper->getUuid($this->storeId);

        if ($uuid != '') {
            $onClickEvent = $this->getClickEvent($uuid);
        } else {
            $onClickEvent = 'javascript:alert(\'Please save config first!\'); return false;';
        }

        return $onClickEvent;
    }

    /**
     * Get Click Event
     *
     * @param string $uuid
     * @return string
     */
    public function getClickEvent($uuid)
    {
        $url = $this->getUrl('waymoreroutee/index/senddata').'?store_id='.$this->storeId.'&uuid='.$uuid;
        //Check if mass data is already transferred over routee.net on 23rd Dec 2019
        $dataTransferred = $this->_scopeConfig->getValue('waymoreroutee/general/datatransferred');

        if ($dataTransferred == $uuid) {
            $onClickEvent = $uuid;
        } else {
            $onClickEvent = 'sendMassDataFunc("'.$url.'")';
        }
        return $onClickEvent;
    }
}
