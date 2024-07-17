<?php

/*
 * This class represents the customer-specific CI
 * within the configuration of its NewGen brochure
 */

class New_Gen_Customer_Ci
{
    /**
     * Loggingobjekt
     *
     * @var Zend_Log
     */
    protected $_logger;

    public $brochureBackgroundColor;
    public $finalPriceColor;
    public $originalPriceColor;
    public $discountTagColor;
    public $discountTextColor;
    public $productTitleTextColor;
    public $productDescriptionTextColor;
    public $iconsAndButtonsColor;
    public $buttonTextColor;
    public $activeObjectColor;
    public $activeObjectTextColor;
    public $inactiveObjectColor;
    public $inactiveObjectTextColor;
    public $pageSeparatorColor;
    public $pageSeparatorTextColor;
    public $clickoutButtonText;

    /**
     * New_Gen_Customer_Ci constructor.
     */
    public function __construct($companyId)
    {
        $this->_logger = Zend_Registry::get('logger');

        $cIConfigs = $this->getConfig($companyId);

        $customerCi = null;
        foreach ($cIConfigs as $cIConfig) {
            if ($cIConfig['company_id'] == $companyId) {
                $this->_logger->info('FOUND customer CI');
                $customerCi = $cIConfig;
                break;
            }
        }

        if ($customerCi == null) {
            $this->_logger->warn('Unable to find CI for: ' . $companyId . ', skipping CI integration');
        }

        foreach ($customerCi as $key => $singleCiElement) {
            if (empty($singleCiElement)) {
                $customerCi[$key] = null;
            }
        }

        $this->brochureBackgroundColor = $customerCi['brochure_background_color'];
        $this->originalPriceColor = $customerCi['original_price_color'];
        $this->finalPriceColor = $customerCi['final_price_color'];
        $this->discountTagColor = $customerCi['discount_tag_color'];
        $this->discountTextColor = $customerCi['discount_text_color'];
        $this->productTitleTextColor = $customerCi['product_title_color'];
        $this->productDescriptionTextColor = $customerCi['product_description_color'];
        $this->iconsAndButtonsColor = $customerCi['icons_and_buttons_color'];
        $this->buttonTextColor = $customerCi['button_text_color'];
        $this->activeObjectColor = $customerCi['active_object_color'];
        $this->activeObjectTextColor = $customerCi['active_object_text_color'];
        $this->inactiveObjectColor = $customerCi['inactive_object_color'];
        $this->inactiveObjectTextColor = $customerCi['inactive_object_text_color'];
        $this->pageSeparatorColor = $customerCi['page_separator_color'];
        $this->pageSeparatorTextColor = $customerCi['page_separator_text_color'];
        $this->clickoutButtonText = $customerCi['clickout_button_text'];
    }

    private function getConfig($companyId)
    {
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        return $sGSRead->getFormattedInfos('18od4BgWPgd6cwocIkz4QlnJuqH3Lfi8quCQCl3JdklU', 'A1', 'R', 'Customer CI');
    }

    public function getCiAsArrayV2()
    {
        return [
            'brochureBackgroundColor' => $this->brochureBackgroundColor,
            'originalPriceColor' => $this->originalPriceColor,
            'finalPriceColor' => $this->finalPriceColor,
            'discountTagColor' => $this->discountTagColor,
            'discountTextColor' => $this->discountTextColor,
            'productTitleColor' => $this->productTitleTextColor,
            'productDescriptionColor' => $this->productDescriptionTextColor,
            'iconsAndButtonsColor' => $this->iconsAndButtonsColor,
            'buttonTextColor' => $this->buttonTextColor,
            'activeObjectColor' => $this->activeObjectColor,
            'activeObjectTextColor' => $this->activeObjectTextColor,
            'inactiveObjectColor' => $this->inactiveObjectColor,
            'inactiveObjectTextColor' => $this->inactiveObjectTextColor,
            'pageSeparatorColor' => $this->pageSeparatorColor,
            'pageSeparatorTextColor' => $this->pageSeparatorTextColor
        ];
    }

    public function getCiAsArrayV3()
    {
        return [
            'brochureBackgroundColor' => $this->brochureBackgroundColor,
            'originalPriceColor' => $this->originalPriceColor,
            'finalPriceColor' => $this->finalPriceColor,
            'discountTagColor' => $this->discountTagColor,
            'discountTextColor' => $this->discountTextColor,
            'productTitleColor' => $this->productTitleTextColor,
            'productDescriptionColor' => $this->productDescriptionTextColor,
            'iconsAndButtonsColor' => $this->iconsAndButtonsColor,
            'buttonTextColor' => $this->buttonTextColor,
            'activeObjectColor' => $this->activeObjectColor,
            'activeObjectTextColor' => $this->activeObjectTextColor,
            'inactiveObjectColor' => $this->inactiveObjectColor,
            'inactiveObjectTextColor' => $this->inactiveObjectTextColor,
            'pageSeparatorColor' => $this->pageSeparatorColor,
            'pageSeparatorTextColor' => $this->pageSeparatorTextColor,
            'clickoutButtonText' => $this->clickoutButtonText
        ];
    }
}
