<?php

/*
 * Store Crawler für dean&david (ID: 71900)
 */

class Crawler_Company_DeanAndDavid_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://deananddavid.de/';
        $searchUrl = $baseUrl . 'wp-content/uploads/wp-google-maps/1markers.xml?u=14';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $aXml = simplexml_load_string($page);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach($aXml->marker as $singleStore) {
            $aAddress = preg_split('#\s*,\s*#', preg_replace('#(\d{5})\s*,\s*(.+)#', '$1 $2', $singleStore->address));
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>\s*(\+[^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore->desc, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }
            
            $pattern = '#>\s*([^<\@]+?\@[^<]+?)\s*<#';
            if (preg_match($pattern, $singleStore->desc, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $singleStore->desc, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $eStore->setStoreNumber((string)$singleStore->marker_id)
                    ->setAddress($aAddress[0], $aAddress[1]);
            
            if (strlen($eStore->getZipcode()) == 5) {
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
