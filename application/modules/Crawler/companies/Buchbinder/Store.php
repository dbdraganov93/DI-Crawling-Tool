<?php

/**
 * Storecrawler für Buchbinder (ID: 28691)
 */
class Crawler_Company_Buchbinder_Store extends Crawler_Generic_Company {

    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     *
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $baseUrl = 'https://www.buchbinder.de/';
        $storeListUrl = $baseUrl . 'de/stationen.html';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aWeekdays = array(
            0 => 'Mo',
            1 => 'Di',
            2 => 'Mi',
            3 => 'Do',
            4 => 'Fr',
            5 => 'Sa',
            6 => 'So'
        );

        $sPage->open($storeListUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a[^>]*href="(de/stationen/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls');
        }

        $aStoreDetailUrls = array();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeCityUrl = $baseUrl . $singleStoreUrl;
            $sPage->open($storeCityUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a\s*href="(de/stationen/[^"]+?)"[^>]*>Zur\s*Station#';
            if (!preg_match_all($pattern, $page, $storeDetailUrlMatches)) {
                $aStoreDetailUrls[] = $storeCityUrl;
                continue;
            }
            foreach ($storeDetailUrlMatches[1] as $singleDetailUrl) {
                $aStoreDetailUrls[] = $baseUrl . $singleDetailUrl;
            }
        }
        $aStoreDetailUrls = array_unique($aStoreDetailUrls);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreDetailUrls as $singleStoreDetailUrl) {
            $sPage->open($singleStoreDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#var\s*offices\s*\=\s*\[(.+?)\];#s';
            if (!preg_match_all($pattern, $page, $storeJsonMatch)) {
                $this->_logger->err($companyId . ': unable to get any stores: ' . $singleStoreDetailUrl);
                continue;
            }

            foreach ($storeJsonMatch[1] as $singleStore) {
                $singleJStore = json_decode($singleStore);
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $strTimes = '';
                foreach ($singleJStore->openingHours as $singleDayKey => $singleDayValue) {
                    if (strlen($strTimes)) {
                        $strTimes .= ', ';
                    }
                    $strTimes .= $aWeekdays[$singleDayKey] . ' ' . $singleDayValue->openingHourAM . '-' . $singleDayValue->closingHourAM;
                }
                
                $eStore->setStoreNumber($singleJStore->officeId)
                        ->setLatitude($singleJStore->lat)
                        ->setLongitude($singleJStore->lng)
                        ->setEmail($sAddress->normalizeEmail($singleJStore->mail))
                        ->setPhone($sAddress->normalizePhoneNumber($singleJStore->phone))
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->address->street)))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->address->street)))
                        ->setZipcode($singleJStore->address->postcode)
                        ->setCity($singleJStore->address->town)
                        ->setStoreHours($sTimes->generateMjOpenings($strTimes));
                
                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
