<?php

/**
 * crawler zur Anreicherung der Öffnungszeiten für Norma (ID: 106)
 *
 * Class Crawler_Company_Norma_Store
 */
class Crawler_Company_Norma_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     *
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sGenerator = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'http://norma-online.de/';
        $searchUrl = $baseUrl . '_d_/_filialfinder_/_suchanfrage_?'
                    . 'x=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LON 
                    . '&y=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_LAT
                    . '&r=100000';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();

        $aUrls = $sGenerator->generateUrl($searchUrl, Marktjagd_Service_Generator_Url::$_TYPE_COORDS, 1);

        foreach ($aUrls as $url) {
            $this->_logger->info('open url: ' . $url);
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();            
                      
            $pattern = '#<tr[^>]*>[^<]*<td[^>]*rowspan="3"[^>]*>[^<]*</td>[^<]*<td[^>]*>(.+?)</td>.+?</tr>[^<]*<tr[^>]*>.+?<table[^>]*>(.+?)</table>#';
            if (preg_match_all($pattern, $page, $match)){
                foreach ($match[0] as $idx => $value){
                    $eStore = new Marktjagd_Entity_Api_Store();
                   
                    if (preg_match('#^(.+?)<br[^>]*>(.+?)$#', $match[1][$idx], $addressMatch)){
                        $eStore->setStreet($sAddress->extractAddressPart('street', preg_replace('#<[^>]*>#', '', $addressMatch[1])))
                                ->setStreetNumber($sAddress->extractAddressPart('street_number', preg_replace('#<[^>]*>#', '', $addressMatch[1])))
                                ->setZipcode($sAddress->extractAddressPart('zipcode', preg_replace('#<[^>]*>#', '', $addressMatch[2])))
                                ->setCity($sAddress->extractAddressPart('city', preg_replace('#<[^>]*>#', '', $addressMatch[2])));
                    }

                    $eStore->setStoreHours($sTimes->generateMjOpenings($match[2][$idx]));                    
               
                    // generieren eine Standortnummer (anhand der Adresse), notwendig für Mapping
                    $eStore->setStoreNumber(md5($eStore->getZipcode() . $eStore->getStreet() . $eStore->getStreetNumber()));
                    
                    $cStores->addElement($eStore);                    
                }
            }
        }            

        // Collection aus UNV generieren
        $sInputApi = new Marktjagd_Service_Input_MarktjagdApi();        
        $cUNVStores = $sInputApi->findStoresByCompany($companyId);
        
        // Standortdaten updaten
        $sCompareStore = new Marktjagd_Service_Compare_Collection_Store();         
        $cStores = $sCompareStore->updateStores($cUNVStores, $cStores);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}