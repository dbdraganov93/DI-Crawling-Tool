<?php

/* 
 * Store Crawler für Ansons (ID: 67926)
 */class Crawler_Company_Ansons_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $baseUrl = 'https://www.ansons.de';
        $searchUrl = '/haeuser/uebersicht';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($baseUrl . $searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<ul\s*class=\"ansons-haeuser-uebersicht\"(.+?)<\/ul>#';
        preg_match($pattern, $page, $tmp);
        $pattern ='#href=\"(\/haeuser\/[^\"]+?)\"#';
        if (!preg_match_all($pattern, $tmp[1], $aLinkMatches)) {
            throw new Exception('Company ID ' . $companyId . ': could not get store urls from ' . $baseUrl . $searchUrl);
        }

        foreach (array_unique($aLinkMatches[1]) as $sSingleStoreLink) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $this->_logger->info('open: ' . $baseUrl . $sSingleStoreLink);
            $sPage->open($baseUrl . $sSingleStoreLink);
            $page = $sPage->getPage()->getResponseBody();
            $eStore->setWebsite($baseUrl . $sSingleStoreLink);

            $pattern ='#<picture.+?srcset=\"([^\"]+desktop[^\"]+\.jpg)#';
            if (!preg_match($pattern, $page, $aImgMatch)) {
                $this->_logger->warn('Company ID ' . $companyId . ': could not get store image from ' . $baseUrl . $sSingleStoreLink);
            } else {
                #$this->_logger->info('imgLink: ' . $aImgMatch[1]);
                $eStore->setImage($aImgMatch[1]);
            }

            $pattern = '#itemprop=\"streetAddress\"[^>]*>([^<]+)<#i';
            if (!preg_match($pattern, $page, $aAddressMatch)) {
                $this->_logger->err('Company ID ' . $companyId . ': could not get store street from ' . $baseUrl . $sSingleStoreLink);
            } else {
                #$this->_logger->info('streetMatch: ' . $aAddressMatch[1]);
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddressMatch[1])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddressMatch[1])));
            }

            $pattern = '#itemprop=\"postalCode\"[^>]*>([^<]+)<#i';
            if (!preg_match($pattern, $page, $aZipMatch)) {
                $this->_logger->err('Company ID ' . $companyId . ': could not get store zip from ' . $baseUrl . $sSingleStoreLink);
            } else {
                #$this->_logger->info('zipMatch: ' . $aZipMatch[1]);
                $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $aZipMatch[1]));
            }

            $pattern = '#itemprop=\"addressLocality\"[^>]*>([^<]+)<#i';
            if (!preg_match($pattern, $page, $aCityMatch)) {
                $this->_logger->err('Company ID ' . $companyId . ': could not get store zip from ' . $baseUrl . $sSingleStoreLink);
            } else {
                #$this->_logger->info('cityMatch: ' . $aCityMatch[1]);
                $eStore->setCity($sAddress->normalizeCity($sAddress->extractAddressPart('city', $aCityMatch[1])));
            }

            $pattern = '#class=\"[^\"]*telefonnummer[^\"]*\".+?field-items\">([^<]+)<#i';
            if (!preg_match($pattern, $page, $aTelMatch)) {
                $this->_logger->warn('Company ID ' . $companyId . ': could not get store phone from ' . $baseUrl . $sSingleStoreLink);
            } else {
                #$this->_logger->info('telMatch: ' . $aTelMatch[1]);
                $eStore->setPhone($sAddress->normalizePhoneNumber($aTelMatch[1]));
            }

            $pattern = '#class=\"[^\"]*faxnummer[^\"]*\".+?field-items\">([^<]+)<#i';
            if (!preg_match($pattern, $page, $aFaxMatch)) {
                $this->_logger->warn('Company ID ' . $companyId . ': could not get store fax from ' . $baseUrl . $sSingleStoreLink);
            } else {
                #$this->_logger->info('faxMatch: ' . $aFaxMatch[1]);
                $eStore->setFax($sAddress->normalizePhoneNumber($aFaxMatch[1]));
            }

            $pattern = '#<div[^>]*ffnungszeiten[^>]*>(.+?)<div[^>]*karte-anzeigen[^>]*>#';
            if (!preg_match($pattern, $page, $aStoreHours)) {
                $this->_logger->err('Company ID ' . $companyId . ': could not get store hours from ' . $baseUrl . $sSingleStoreLink);
            } else {
                #$this->_logger->info('storeHourMatch: ' . $aStoreHours[1]);
                $eStore->setStoreHours($sTimes->generateMjOpenings($aStoreHours[1]));
            }

            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
 }
