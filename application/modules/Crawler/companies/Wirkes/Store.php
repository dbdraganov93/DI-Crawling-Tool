<?php

/* 
 * Store Crawler für Wirkes (ID: 71855)
 */

class Crawler_Company_Wirkes_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://trachtenshop.de/';
        $searchUrl = $baseUrl . 'StoreLocator/search?lat=0&lng=0&distance=0&catFilter=&byname=';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*infobox\s*=\s*"(<[^>]*>)?([^"]+?)";.+?ffnungszeiten(.+?)</p#s';
        if(!preg_match_all($pattern, $page, $storeMatches)){
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        for ($i= 0; $i < count($storeMatches[0]); $i++)
        {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $aInfos = preg_split('#(\s*<[^>]*>\s*)+#', $storeMatches[2][$i]);
            
            $eStore->setAddress($aInfos[1], $aInfos[2])
                    ->setPhoneNormalized($aInfos[3])
                    ->setStoreHoursNormalized($storeMatches[3][$i]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
        
    }
}