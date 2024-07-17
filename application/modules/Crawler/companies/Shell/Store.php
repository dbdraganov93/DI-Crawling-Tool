<?php

class Crawler_Company_Shell_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://locator.shell.co.uk/';
        $searchUrl = $baseUrl . 'radial_results';
        $sPage = new Marktjagd_Service_Input_Page(true);

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $oPage->setUseCookies(true);
        $sPage->setPage($oPage);
        $aParams = array(
            'launch_countrycode' => 'DE',
            'site_mode' => 'rtl',
            'service_id' => 9,
            'lat' => '51.05754959999999',
            'lng' => '13.717064800000003',
            'results' => 100,
            'selection_filter' => 'TRUE+AND+flag_isActive+IS+TRUE++AND+local_tpn%3DFALSE+%20AND%20'
                . 'flag_type_shell%3DTRUE%20%20AND%20not%20flag_type_crtm%3DTRUE%20',
            'authenticity_token' => 'urwgqqPICNspClF1cs3AHQQjg5AADOJx7hYG1bbxUno='
        );

        $sPage->open($searchUrl, $aParams);
        $page = $sPage->getPage()->getResponseBody();
        Zend_Debug::dump($sPage->getPage()->getClient()->getLastResponse()->getRawBody());die;

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aStoreUrls as $singleStoreUrl) {
            $oPage = $sPage->getPage();
            $oPage->setMethod('GET');
            $sPage->setPage($oPage);

            $storeDetailUrl = $baseUrl . 'standorte/' . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#icon-info(.+?)<a\s*href="\/termin-vereinbaren\/"#s';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->info($companyId . ': unable to get store info: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#<p[^>]*>\s*(.+?)\s*([0-9]{5}.+?)\s*<#';
            if (!preg_match($pattern, $storeInfoMatch[1], $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#ffnungszeiten(.+?)</p#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }

            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $storeAddressMatch[1])))
                ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $storeAddressMatch[1])))
                ->setCity($sAddress->extractAddressPart('city', $storeAddressMatch[2]))
                ->setZipcode($sAddress->extractAddressPart('zipcode', $storeAddressMatch[2]));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}