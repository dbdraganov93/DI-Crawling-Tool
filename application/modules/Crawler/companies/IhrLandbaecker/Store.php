<?php

/**
 * Store Crawler für Ihr Landbäcker (ID: 68927)
 */

class Crawler_Company_IhrLandbaecker_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ihrlandbaecker.de/';
        $searchUrl = $baseUrl . 'filialen-gesamtuebersicht/';

        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
                
        $pattern = '#<strong[^>]*>IHR\s*LANDBÄCKER\s*(.+?)<a[^>]*#';
        if (!preg_match_all($pattern, $page, $aStoreMatches)) {
            $this->_logger->log($companyId . ': unable to find any stores.', Zend_Log::CRIT);
        }
        
        $cStore = new Marktjagd_Collection_Api_Store();

        foreach ($aStoreMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $sTimes = new Marktjagd_Service_Text_Times();
            $sAddress = new Marktjagd_Service_Text_Address();

            $aData = preg_split('#<br[^>]*>#', $singleStore);
            $aAddress = preg_split('#\,#', $aData[1]);

            $eStore ->setZipcode($sAddress->extractAddressPart('zip', $aAddress[0]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[0]))
                    ->setStreet($sAddress->extractAddressPart('street', $aAddress[1]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($aAddress[2]))
                    ->setStoreHours($sTimes->generateMjOpenings(preg_replace(array('#F r#i', '#Fr,#i'), array('Fr', 'Fr'), $aData[2])));
            
            $cStore->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}