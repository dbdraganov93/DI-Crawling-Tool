<?php

/*
 * Store Crawler für EuroEyes (ID: 69754)
 */

class Crawler_Company_EuroEyes_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.euroeyes.de/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<section[^>]*id="Standorte-Liste"[^>]*>(.+?)</section>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*title=[^>]*#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store url from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#teaser-location.+?<div[^>]*class="entry-summary"[^>]*>\s*<p[^>]*>\s*(.+?)\s*</div#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->info($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                continue;
            }
            
            $aInfos = preg_split('#(\s*<[^>]*>\s*)+#', $infoListMatch[1]);
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            for ($i = 0; $i < count($aInfos); $i++) {
                if (preg_match('#^\d{5}#', $aInfos[$i])) {
                    $eStore->setAddress($aInfos[$i - 1], $aInfos[$i]);
                    continue;
                }
                
                if (preg_match('#telefon#i', $aInfos[$i])) {
                    $eStore->setPhoneNormalized($aInfos[$i]);
                    continue;
                }
                
                if (preg_match('#fax#i', $aInfos[$i])) {
                    $eStore->setFaxNormalized($aInfos[$i]);
                    continue;
                }
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
