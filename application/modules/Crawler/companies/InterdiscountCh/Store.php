<?php

/*
 * Store Crawler für Interdiscount CH (ID: 72166)
 */

class Crawler_Company_InterdiscountCh_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.interdiscount.ch/';
        $searchUrl = $baseUrl . 'idshop/pages/storeLocator.jsf';
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDbGeo->findAll('CH');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Referer: https://www.interdiscount.ch/idshop/pages/storeLocator.jsf',
            'Origin: https://www.interdiscount.ch',
            'Cookie: JSESSIONID=0577C8EAD611CC24B17BD2D970CFEC2E.p02; visid_incap_946352=bG6cNHEyRp+qMxF+c7I1KaryglkAAAAAQUIPAAAAAADksETgL3B1F66B/bDmP53s; incap_ses_472_946352=+UMELJBgsVXYCAMckOGMBlO4klkAAAAAlqBphsoobp5GVJXB3ro8mA==; _gat_UA-27287657-1=1; _uetsid=_uet02620775; _ga=GA1.2.1978848843.1501754028; _gid=GA1.2.637076934.1502787669; WT_FPC=id=10.7.4.10-1742097984.30608446:lv=1502784119319:ss=1502784068669'
        ));
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'searchForm%3Aplz=4132&searchForm%3Astadt=&searchForm%3AcircleSearch=20&searchForm_SUBMIT=1&searchForm%3A_link_hidden_=&searchForm%3A_idcl=searchForm%3A_id466&javax.faces.ViewState=rO0ABXVyABNbTGphdmEubGFuZy5PYmplY3Q7kM5YnxBzKWwCAAB4cAAAAAN0AAhfaWQxOTk5OXB0ABkvcGFnZXMvc3RvcmVMb2NhdG9yLnhodG1s');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        curl_close($ch);
        
        Zend_Debug::dump($response);die;


        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $aParams['searchForm:plz'] = '4132';

            $sPage->open($searchUrl, $aParams);
            $page = $sPage->getPage()->getResponseBody();

            Zend_Debug::dump($page);
            die;
        }
    }

}
