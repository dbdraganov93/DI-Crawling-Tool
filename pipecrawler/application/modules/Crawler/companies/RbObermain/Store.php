<?php

/*
 * Store Crawler für Raiffeisenbank Obermain Nord (ID: 71779)
 */

class Crawler_Company_RbObermain_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.rbobermain.de/';
        $searchUrl = $baseUrl . 'wir-fuer-sie/filialen-ansprechpartner/filialen/uebersicht-filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="(https:\/\/www\.rbobermain\.de/wir-fuer-sie/filialen-ansprechpartner/filialen/uebersicht-filialen/[^"]+?\.html)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches))
        {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl)
        {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $singleStoreUrl);
                continue;
            }
            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);
            
            $pattern = '#<time[^>]*itemprop="openingHours"[^>]*>\s*([^<]+?Uhr[^<]*?)\s*<#s';
            if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                $strTimes = implode(',', $storeHoursMatches[1]);
            }
            
            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                    ->setZipcode($aInfos['postalCode'])
                    ->setCity($aInfos['addressLocality'])
                    ->setPhoneNormalized($aInfos['telephone'])
                    ->setFaxNormalized($aInfos['faxNumber'])
                    ->setStoreHoursNormalized($strTimes);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
