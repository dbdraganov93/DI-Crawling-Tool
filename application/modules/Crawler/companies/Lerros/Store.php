<?php

/* 
 * Store Crawler für Lerros (ID: 71802)
 */

class Crawler_Company_Lerros_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.lerros.com/';
        $searchUrl = $baseUrl . '?cl=storefindersearch&lat=45&lng=10&radius=1000';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $xmlStores = new SimpleXMLElement($page);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($xmlStores as $singleStore)
        {
            $aInfos = $singleStore->attributes();
            if (!preg_match('#^D#', (string)$aInfos['country']))
            {
                continue;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setSubtitle(ucwords(strtolower((string)$aInfos['name'])))
                    ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', (string)$aInfos['street'])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', (string)$aInfos['street'])))
                    ->setZipcode((string)$aInfos['postcode'])
                    ->setCity(ucwords(strtolower((string)$aInfos['town'])))
                    ->setWebsite((string)$aInfos['website_url'])
                    ->setPhone($sAddress->normalizePhoneNumber((string)$aInfos['phone']))
                    ->setLatitude((string)$aInfos['lat'])
                    ->setLongitude((string)$aInfos['lng'])
                    ->setImage((string)($aInfos['picture']));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}