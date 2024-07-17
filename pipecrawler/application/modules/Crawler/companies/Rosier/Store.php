<?php

/*
 * Store Crawler für Rosier (ID: 71845)
 */

class Crawler_Company_Rosier_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.rosier.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $cStores = new Marktjagd_Collection_Api_Store();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
       
        if (!preg_match_all('#href="(unternehmen/standorte/[^"]+)"#', $page, $matches)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $matches[1] = array_unique($matches[1]);

        foreach ($matches[1] as $cityLink) {
            $sPage->open($baseUrl . $cityLink);
            $page = $sPage->getPage()->getResponseBody();

            if (preg_match_all('#<h4>\s*<a[^>]*>(.+?)\s*</a>\s*</h4>\s*<p>\s*<strong>(.+?)</strong>(.+?)</p>#', $page, $match)){
                foreach ($match[1] as $idx => $storeTitle){
                    $eStore = new Marktjagd_Entity_Api_Store();
             
                    $eStore->setTitle(trim($storeTitle) . ' - ' . $match[2][$idx])
                            ->setWebsite($baseUrl . $cityLink);
                    
                    $addressLines = preg_split('#<br[^>]*>#', $match[3][$idx]);
                                        
                    
                    $eStore->setStreetAndStreetNumber($addressLines[2])
                            ->setZipcodeAndCity($addressLines[3])
                            ->setPhoneNormalized($addressLines[4])
                            ->setFaxNormalized($addressLines[5]);
                    
                    $cStores->addElement($eStore);
                }
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
