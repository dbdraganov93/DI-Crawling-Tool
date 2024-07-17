<?php

/*
 * This class represents the customer-specific assets for a NewGen brochure, e.g. images and videos
 */

class New_Gen_Customer_Assets
{
    private $CUSTOMER_ASSET_TYPES_BANNER = 'banner_1';
    private $CUSTOMER_ASSET_TYPES_VIDEO = 'video_1';
    private $CUSTOMER_ASSET_POSITIONS = ['top', 'bottom', 'random'];
    public $assets;

    public function __construct($companyId)
    {
        $this->_logger = Zend_Registry::get('logger');
        $this->assets = $this->getCustomerAssets($companyId);
    }

    private function getAssetsGoogleSheet()
    {
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        return $sGSRead->getFormattedInfos('18od4BgWPgd6cwocIkz4QlnJuqH3Lfi8quCQCl3JdklU', 'A1', 'H', 'Videos-Banner-Gifs');
    }

    private function getCustomerAssets($companyId)
    {
        $this->_logger->info('Looking for customer assets');
        $assetsGoogleSheet = $this->getAssetsGoogleSheet();
        $filteredAssetsForCompany = array_filter($assetsGoogleSheet, function ($v) use($companyId) {
            if ($v['company_id'] == $companyId) {
                return true;
            } else {
                return false;
            }
        }, ARRAY_FILTER_USE_BOTH);

        return $this->parseCustomerAssets($filteredAssetsForCompany);
    }

    private function parseCustomerAssets($filteredForCompany)
    {
        return array_filter($filteredForCompany, function($v, $k){
            /*
             * If the type is not explicitly set,
             * the assets gets filtered out
             */
            if (!key_exists('type', $v)) {
                $this->_logger->err('Asset key "type" does not exist for asset: ' . $k);
                return false;
            /*
             * If the type is set and it is not matching the compatible type,
             * the assets gets filtered out
             */
            } elseif (!in_array($v['type'], [$this->CUSTOMER_ASSET_TYPES_BANNER, $this->CUSTOMER_ASSET_TYPES_VIDEO])) {
                $this->_logger->err('Asset type: ' .  $v['type'] . ' ist not compatible for asset: ' . $k);
                return false;
            /*
             * If the position is not set explicitly,
             * the assets gets filtered out
             */
            } elseif (!key_exists('position', $v)) {
                $this->_logger->err('Asset key "position" does not exist for asset: ' . $k);
                return false;
            /*
             * If the position is set and it is not matching compatible positions,
             * the assets gets filtered out
             */
            } elseif (!in_array($v['position'], $this->CUSTOMER_ASSET_POSITIONS)) {
                $this->_logger->err('Asset position: ' . $v['position'] . ' is not compatible for asset: ' . $k);
                return false;
            /*
             * If the source url is no set explicitly,
             * the assets gets filtered out
             */
            } elseif (!key_exists('source_url', $v) or empty($v['source_url'])) {
                $this->_logger->err('Asset key "source_url" does not exist for asset: ' . $k);
                return false;
            /*
             * If the type is video and the thumbnail url is not set explicitly,
             * the assets gets filtered out
             */
            } elseif (($v['type'] === $this->CUSTOMER_ASSET_TYPES_VIDEO) and (!key_exists('video_thumbnail_url', $v) or empty($v['video_thumbnail_url']))) {
                $this->_logger->err('Asset key "video_thumbnail_url" for ' . $this->CUSTOMER_ASSET_TYPES_VIDEO . ' is empty for asset: ' . $k);
                return false;
            } else {
                return true;
            }
        }, ARRAY_FILTER_USE_BOTH);
    }
}
