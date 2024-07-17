<?php

/*
 * Store Crawler für Tonerdumping (ID: 71356)
 */

class Crawler_Company_Tonerdumping_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.toner-dumping.de/';
        $searchUrl = $baseUrl . 'filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*id="filiinner"[^>]*>(.+?)</div#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="(filialen\/[^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeCountyMatches)) {
            throw new Exception($companyId . ': unable to get store counties from list.');
        }

        $aDetailUrls = array();
        foreach ($storeCountyMatches[1] as $singleStoreCounty) {
            $sPage->open($baseUrl . $singleStoreCounty);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*class="fililink[^>]*href="(filialen\/[^"]+?)"#';
            if (preg_match_all($pattern, $page, $storeLinkMatches)) {
                $aDetailUrls = array_merge($aDetailUrls, $storeLinkMatches[1]);
            }
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aDetailUrls as $singleDetailUrl) {
            $storeDetailUrl = $baseUrl . $singleDetailUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<script[^>]*type="application\/ld\+json"[^>]*>\s*([^<]+?)\s*</script#s';
            if (!preg_match($pattern, $page, $detailJsonMatch)) {
                $this->_logger->err($companyId . ': unable to get store details: ' . $storeDetailUrl);
                continue;
            }
            
            $storeInfos = json_decode($detailJsonMatch[1]);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $strTimes = '';
            foreach ($storeInfos->openingHoursSpecification as $singleDay) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $sTimes->convertToGermanDays($singleDay->dayOfWeek) . ' ' . $singleDay->opens . '-' . $singleDay->closes;
            }
            
            $eStore->setStreetAndStreetNumber($storeInfos->address->streetAddress)
                    ->setCity($storeInfos->address->addressLocality)
                    ->setZipcode($storeInfos->address->postalCode)
                    ->setLatitude($storeInfos->geo->latitude)
                    ->setLongitude($storeInfos->geo->longitude)
                    ->setPhoneNormalized($storeInfos->telephone)
                    ->setStoreHoursNormalized($strTimes);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
