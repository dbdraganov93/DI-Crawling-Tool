<?php

/**
 * Store Crawler für Vero Moda (ID: 67872)
 */
class Crawler_Company_VeroModa_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.veromoda.com/';
        $searchUrl = $baseUrl . 'on/demandware.store/Sites-ROE-Site/de_DE/StoreLocator-getJSON?param=cities%3Fcountry%3DDEU%26brand%3DVM%26storetype%3Dretail';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $jStores = json_decode($output);
                
        Zend_Debug::dump($output);die;
    }
}