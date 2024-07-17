<?php

/*
 * Store Crawler für Lloyd (ID: 68890)
 */

class Crawler_Company_Lloyd_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.lloyd.com/';
        $searchUrl = $baseUrl . 'storefinder/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $aParams = array(
            'tx_lloyddealers_lloyddealers[country]' => '54',
            'tx_lloyddealers_lloyddealers[type]' => '6',
            'tx_lloyddealers_lloyddealers[action]' => 'listForCity',
            'tx_lloyddealers_lloyddealers[controller]' => 'Dealer',
            'cHash' => 'f32670172b9f109a555d375e1773ae0b'
        );
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*storeDescription\s*=\s*\[(.+?)\];#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . '. unable to get store list.');
        }
        
        $pattern = '#href=\\\"\\\/[^\"]+?germany\\\/listForCity\\\/(.+?)\\\/#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeCityMatches)) {
            throw new Exception ($companyId . '. unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeCityMatches[1] as $singleStoreCity) {
            $oPage = $sPage->getPage();
            $oPage->setMethod('POST');
            $sPage->setPage($oPage);
            $aParams['tx_lloyddealers_lloyddealers[city]'] = $singleStoreCity;
            
            $sPage->open($searchUrl . 'lloyd-stores/', $aParams);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="dealer"[^>]*>(.+?)</div#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': unable to get stores: ' . $singleStoreCity);
                continue;
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#>([^<]+?)<[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }
                $aStreet = preg_split('#\s*,\s*#', $addressMatch[1]);
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#Tel([^<]+?)<#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }
                
                $pattern = '#Fax([^<]+?)<#';
                if (preg_match($pattern, $singleStore, $faxMatch)) {
                    $eStore->setFaxNormalized($faxMatch[1]);
                }
                
                $pattern = '#mailto:([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $mailMatch)) {
                    $eStore->setEmail($mailMatch[1]);
                }
                
                $pattern = '#ffnungszeiten(.+)#';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }
                
                $pattern = '#maps\.google\.de\/maps\?q=([^,]+?),([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $geoMatch)) {
                    $eStore->setLatitude($geoMatch[1])
                            ->setLongitude($geoMatch[2]);
                }
                
                $eStore->setAddress(end($aStreet), $addressMatch[2]);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
