<?php

/* 
 * Store Crawler für Schöffel Lowa (ID: 71098)
 */

class Crawler_Company_Schoeffel_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.schoeffel.de/';
        $searchUrl = $baseUrl . 'service/storefinder/schoeffel-lowa-stores/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#<div[^>]*class="map_list"[^>]*>\s*<ul>\s*(.*?)\s*</ul>#is', $page, $matchStores)) {
            throw new Exception ($companyId . ': cannot match any stores');
        }

        $pattern = '#<li>.*?<p>\s*(.*?)\s*<br\s*[/]*>\s*(.*?)\s*</p>(.*?)</li>#is';
        if (!preg_match_all($pattern, $matchStores[1],$matchStoreInfos))
        {
            throw new Exception ($companyId . ': unable to get store detail infos');
        }

        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($matchStoreInfos[0] as $key => $storeInfo)
        {
            $aZipCity = preg_split('#\-\s+#', $matchStoreInfos[2][$key], 2);
            if ($aZipCity[0] != 'DE') {
                $this->_logger->info($companyId . ': not a german store. skipping');
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStreetAndStreetNumber($matchStoreInfos[1][$key])
                    ->setZipcodeAndCity($aZipCity[1])
                    ->setBonusCard('Schöffel-LOWA-BonusCard');

            if (preg_match('#<p>Tel\:(.*?)</p>#is', $storeInfo, $matchPhone)) {
                $eStore->setPhoneNormalized($matchPhone[1]);
            }

            if (preg_match('#mailto:(.*?)"#is', $storeInfo, $matchMail)) {
                $eStore->setEmail($matchMail[1]);
            }
            
            $cStores->addElement($eStore);            
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}