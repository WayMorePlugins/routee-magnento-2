<?php

namespace Routee\WaymoreRoutee\Block\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Routee\WaymoreRoutee\Helper\Data;

class SendProductMassData extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Routee_WaymoreRoutee::system/config/sendProductsMassData.phtml';

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
                    'id' => 'product_mass_data',
                    'class' => 'primary send_mass_data',
                    'label' => __('Start Syncing'),
                    'disabled' => $this->productSynced()
                ]
            )
            ->setDataAttribute(
                [
                    'action' => 'product_data'
                ]
            );
        return $button->toHtml();
    }

    /**
     * @return string
     */
    public function getAjaxUrl()
    {
        $uuid = $this->getUUID();
        return $this->getUrl('waymoreroutee/index/syncmassdata').'?store_id='.$this->storeId.'&uuid='.$uuid;
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getUUID()
    {
        $this->storeId = $this->_storeManager->getStore()->getId();
        return $this->helper->getUuid($this->storeId);
    }

    /**
     * @return bool
     */
    public function productSynced()
    {
        $path = "waymoreroutee/general/productmass";
        return !empty($this->helper->getConfigValue($path));
    }

    /**
     * @return bool
     */
    public function completeDataSynced()
    {
        $data = [
            'productmass',
            'customermass',
            'ordermass',
            'subscribermass'.
            'wishlistmass'
        ];
        foreach ($data as $datum) {
            $path = "waymoreroutee/general/" . $datum;
            if (empty($this->helper->getConfigValue($path))) {
                return false;
            }
        }
        return true;
    }
}
