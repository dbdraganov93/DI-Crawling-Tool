<?php

/* 
 * Store Crawler für Landmarkt (ID: 71851)
 */

class Crawler_Company_Landmarkt_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.landmarkt-portal.de/';
        $searchUrl = $baseUrl . 'unsere-landmaerkte';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*contentString\d*\s*=\s*\'(.+?)\';#s';
        if (!preg_match_all($pattern, $page, $storeMatches))
        {
            throw new Exception ($companyId . ': unable to get any stores.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore)
        {
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)+(\d{5}[^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $storeAddressMatch))
            {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }
                        
            $pattern = '#>\s*([^<]+?\@[^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore, $storeMailMatch))
            {
                $eStore->setEmail($storeMailMatch[1]);
            }
            
            $pattern = '#tel(.+?)</tr#i';
            if (preg_match($pattern, $singleStore, $storePhoneMatch))
            {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }
            
            $pattern = '#fax(.+?)</tr#i';
            if (preg_match($pattern, $singleStore, $storeFaxMatch))
            {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }
            
            $pattern = '#a\s*href=\\\"([^\\\]+?)\\\"#';
            if (preg_match($pattern, $singleStore, $storeWebsiteMatch))
            {
                $eStore->setWebsite($storeWebsiteMatch[1]);
            }
            
            $eStore->setAddress($storeAddressMatch[1], $storeAddressMatch[3]);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}