<?php

/*
 * Store Crawler für Frischecenter Zurheide (ID: 71894)
 */

class Crawler_Company_Zurheide_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://frischecenter-zurheide.de/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class=\'avia_textblock[^\']*\'[^>]*itemprop="text"[^>]*>\s*<h[^>]*>(.+?)</div>\s*</div#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from list: ' . $searchUrl);
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>([^<]+?)<[^>]*>(\s*\d{5}\s+[^<]+?)<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get address: ' . $singleStore);
                continue;
            }
            
            $pattern = '#>([^<]+?Uhr\s*)<#';
            if (preg_match_all($pattern, $singleStore, $storeHoursMatches)) {
                $eStore->setStoreHoursNormalized(implode(',', $storeHoursMatches[1]));
            }
            
            $pattern = '#tel([^<]+?)<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#<img[^>]*src=\'([^\']+?)\'#';
            if (preg_match($pattern, $singleStore, $imageMatch)) {
                $eStore->setImage($imageMatch[1]);
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
