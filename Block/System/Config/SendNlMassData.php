<?php

namespace Routee\WaymoreRoutee\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
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
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData(
            [
                'id' => 'send_mass_data',
                'label' => __('Start Sync')
            ]
        );
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function dataSynced()
    {
        $path = "waymoreroutee/general/subscribermass";
        return !empty($this->helper->getConfigValue($path));
    }
}
