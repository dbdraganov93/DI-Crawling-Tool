<?php

/*
 * Store Crawler für Lotto BW (ID: 71773)
 */

class Crawler_Company_LottoBw_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.lotto-bw.de/';
        $searchUrl = $baseUrl . 'controller/RetailerController/showShopList?gbn=3';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDbGeoRegion->findAllZipCodes();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array(
            'town' => '',
            'preselection' => ''
        );

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['zip'] = $singleZipcode;

            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#var\s*gStoreData\s*=\s*\[\s*([^\]]+?)\s*\]#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no stores for zip: ' . $singleZipcode);
                continue;
            }

            $pattern = '#\{([^\}]+?)\}#';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
                $this->_logger->info($companyId . ': no stores from list for zip: ' . $singleZipcode);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#\s*([^:,\s]+):\s*\'?\"?([^,\'\"]+)\'?\"?\s*#';
                if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                    $this->_logger->err($companyId . ': unable to get store infos for: ' . $singleStore);
                    continue;
                }

                $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setStoreNumber($aInfos['storeid'])
                    ->setLatitude($aInfos['latitude'])
                    ->setLongitude($aInfos['longitude'])
                    ->setStreetAndStreetNumber($aInfos['street'])
                    ->setZipcode($aInfos['zip'])
                    ->setCity(trim($aInfos['city']))
                    ->setPhoneNormalized($aInfos['telephone']);

                if ($eStore->getLatitude() != '0'
                    && $eStore->getLongitude() != '0') {
                    $eStore->setLatitude($aInfos['latitude'])
                        ->setLongitude($aInfos['longitude']);
                }

                $cStores->addElement($eStore, TRUE);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
