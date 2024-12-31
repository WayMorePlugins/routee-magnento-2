<?php

namespace Routee\WaymoreRoutee\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Routee\WaymoreRoutee\Helper\Data;

class SendNlMassData extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Routee_WaymoreRoutee::system/config/sendSubscribersMassData.phtml';

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
                    'id' => 'sub_mass_data',
                    'class' => 'primary send_mass_data',
                    'label' => __('Start Syncing'),
                    'disabled' => !$this->orderSynced() || $this->subscriberSynced()
                ]
            )
            ->setDataAttribute(
                [
                    'action' => 'subscriber_data'
                ]
            );
        return $button->toHtml();
    }

    /**
     * @return bool
     */
    public function subscriberSynced()
    {
        $path = "waymoreroutee/general/subscribermass";
        return !empty($this->helper->getConfigValue($path));
    }

     /**
      * @return bool
      */
    public function orderSynced()
    {
        $path = "waymoreroutee/general/ordermass";
        return !empty($this->helper->getConfigValue($path));
    }
}
