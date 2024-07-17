<?php

/* 
 * Store Crawler für Guter Name (ID: 67940)
 */

class Crawler_Company_GuNa_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.kult-olymp-hades.de';
        $searchUrl = $baseUrl . '/StoreLocator/search?lat=51.0553&lng=13.726049999999987&distance=0&country=DE&catFilter=&byname=';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $mapStoreCompany = array (
            'elb' => '71068',
            'kult' => '71891',
            'olymp & hades' => '71067'
        );
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        
        if (!preg_match_all('#<div[^>]*class="[^"]*store-details[^"]*"[^>]*>(.+?)</div>#', $page, $storeMatches)){
            throw new Exception('cannot find any stores on ' . $searchUrl);
        }
        
        foreach ($storeMatches[1] as $storeMatch)
        {
            $eStore = new Marktjagd_Entity_Api_Store();
                        
            if (preg_match('#<h2>(.+?)</h2>#', $storeMatch, $match)){
                $eStore->setTitle($match[1]);
            }
            
            $addressLines = preg_split('#<br[^>]*>#', preg_replace('#^.*?<p>(.+?)</p>#', '$1', $storeMatch));
            
            $eStore->setStreetAndStreetNumber($addressLines[0])
                    ->setZipcodeAndCity($addressLines[1]);
            
            foreach ($addressLines as $addressLine){
                if (preg_match('#^\s*tel#i', $addressLine, $match)){
                    $eStore->setPhoneNormalized($addressLine);
                }
                
                if (preg_match('#^\s*fax#i', $addressLine, $match)){
                    $eStore->setFaxNormalized($addressLine);
                }
                
                if (preg_match('#href="(http[^"]+)"#i', $addressLine, $match)){
                    $eStore->setWebsite($match[1]);
                }
                
                if (preg_match('#href="mailto:([^"]+)"#i', $addressLine, $match)){
                    $eStore->setEmail($match[1]);
                }
            }
            
            
            if (preg_match('#^0?43#', $eStore->getPhone()) || preg_match('#^0?43#', $eStore->getFax()) || strlen($eStore->getZipcode()) != 5){
                continue;
            }
            
            
            if (array_key_exists(strtolower($eStore->title), $mapStoreCompany)){
                if ($companyId == $mapStoreCompany[strtolower($eStore->title)]) {
                    $cStores->addElement($eStore);             
                } else {
                    continue;
                }
            }

            if ($companyId == 67940){
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}