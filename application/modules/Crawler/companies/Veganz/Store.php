<?php

/* 
 * Store Crawler für Veganz (ID: )
 */

class Crawler_Company_Veganz_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://veganz-blog.com/';
        $searchUrl = $baseUrl . 'de/filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*wpgmaps_localize_marker_data\s*=\s*\{\"1\":([^\]]+?\])#';
        if(!preg_match($pattern, $page, $storeListMatch))
        {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $jStores = json_decode($storeListMatch[1]);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore)
        {
            if (!preg_match('#Veganz#', $singleJStore->title))
            {
                continue;
            }
            
            if (!preg_match('#Deutschland#', $singleJStore->address))
            {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $aAddress = preg_split('#\s*\,\s*#', $singleJStore->address);
            $aStoreHoursContact = preg_split('#\s*<br[^>]*>\s*<br[^>]*>\s*#', $singleJStore->desc);
            
            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aStoreHoursContact[0]))
                    ->setStoreHours($sTimes->generateMjOpenings($aStoreHoursContact[1]))
                    ->setLatitude($singleJStore->lat)
                    ->setLongitude($singleJStore->lng)
                    ->setImage($singleJStore->pic);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}