<?php

/**
 * Store Crawler für Fiat (ID: 68798)
 */
class Crawler_Company_Fiat_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://dealerlocator.fiat.com/';
        $searchUrl = $baseUrl . 'geocall/RestServlet'
                . '?jsonp=callback'
                . '&mkt=3110'
                . '&brand=00'
                . '&func=finddealerxml'
                . '&address=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&dlr=1&org=1&rad=100';
           
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aLinks = $sGen->generateUrl($searchUrl, 'zip', 80);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aLinks as $singleLink) {
            if (!$sPage->open($singleLink)) {
                throw new Exception ($companyId . ': unable to open store list page. url: ' . $singleLink);
            }
            
            $jStores = json_decode(preg_replace(array('#callback\(#', '#\)#'), array('', ''), $sPage->getPage()->getResponseBody()), true);

            foreach ($jStores['results'] as $jStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setFax($jStore['FAX'])
                        ->setLatitude($jStore['YCOORD'])
                        ->setLongitude($jStore['XCOORD'])
                        ->setCity($jStore['TOWN'])
                        ->setZipcode($jStore['ZIPCODE'])
                        ->setPhone($jStore['TEL_1'])                      
                        ->setSubtitle($jStore['COMPANYNAM'])
                        ->setEmail($jStore['EMAIL'])
                        ->setStreet($sAddress->extractAddressPart('street', $jStore['ADDRESS']))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', $jStore['ADDRESS']));

                $cStores->addElement($eStore);
            }
        }                        
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}