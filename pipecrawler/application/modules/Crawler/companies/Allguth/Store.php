<?php

/*
 * Storecrawler für ALLGUTH (ID: 71613)
*/
class Crawler_Company_Allguth_Store extends Crawler_Generic_Company
{
    
    public function crawl($companyId) {
        
        $baseUrl = 'https://www.allguth.de/';
        $searchUrl = $baseUrl . 'stationsfinder.html';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);                
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<li[^>]*>\s*<a[^>]*href="(stationsfinder\/details\/[^"]+)"#';
        if (!preg_match_all($pattern, $page, $stationUrlMatches)){
            throw new Exception($companyId . ': cannot find any station information');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($stationUrlMatches[1] as $stationPage){
            $storeDetailUrl = $baseUrl . $stationPage;

            $sPage->open($storeDetailUrl); 
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<a[^>]*href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#fax:?([^<]+?)<#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#Kartenakzeptanz\s*</h4>\s*([^<]+?)\s*<#s';
            if (preg_match($pattern, $page, $paymentMatch)) {
                $eStore->setPayment($paymentMatch[1]);
            }
            
            $pattern = '#Ausstattung(.+?)</ul#';
            if (preg_match($pattern, $page, $sectionListMatch)) {
                $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                    $eStore->setSection(implode(', ', $sectionMatches[1]));
                }
            }
            
            $pattern = '#Filialservice(.+?)</ul#';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }
            
            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($storeDetailUrl);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}