<?php

/* 
 * Store Crawler für Parfümerie Pieper (ID: 71783)
 */

class Crawler_Company_Pieper_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.parfuemerie-pieper.de/';
        $searchUrl = $baseUrl . 'filialfinder.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#var\s*locations\s*=\s*(.+?\]);#s';
        if (!preg_match($pattern, $page, $storeJsonMatch))
        {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeJsonMatch[1]) as $singleJStore)
        {
            if (!preg_match('#Deutschland#', $singleJStore->country)) {
                continue;
            }
                        
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->gmapstrlocator_id)
                    ->setCity($singleJStore->district)
                    ->setZipcode($singleJStore->postal_code)
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleJStore->address)))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleJStore->address)))
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setPhone($sAddress->normalizePhoneNumber($singleJStore->store_phone))
                    ->setFax($sAddress->normalizePhoneNumber($singleJStore->store_fax))
                    ->setStoreHours($sTimes->generateMjOpenings($singleJStore->store_description))
                    ->setSection(implode(', ', preg_split('#\s*<[^>]*>\s*#', preg_replace(array('#<[^>]*span[^>]*>#', '#(\s*<[^>]*br[^>]*>\s*)$#'), array('', ''), $singleJStore->attributes_list))));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}