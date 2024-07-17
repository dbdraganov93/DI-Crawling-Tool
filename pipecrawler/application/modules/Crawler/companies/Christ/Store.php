<?php

/**
 * Store Crawler für Christ (ID: 280)
 */
class Crawler_Company_Christ_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.christ.de/';
                
        $finderUrl = $baseUrl . 'pages/christ/servicePage.jsf#filialsuche';
                                
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $sPage->open($finderUrl);
        $pageConf = $sPage->getPage();
        $pageConf->setUseCookies(true);
        $pageConf->setMethod('POST');
        
        $client = $pageConf->getClient();
        $client->setHeaders('Upgrade-Insecure-Requests', '1');
        $client->setHeaders('Referer', 'http://www.christ.de/pages/christ/servicePage.jsf');
        $client->setHeaders('Host', 'www.christ.de');
        $client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
        
        $pageConf->setClient($client);                
        $sPage->setPage($pageConf);
        
        $page = $sPage->getPage()->getResponseBody();
                
        if (!preg_match('#<input[^>]*name=\"javax.faces.ViewState\"[^>]*value=\"([^\"]+)\"#', $page, $viewStateMatch)){
            throw new Exception('no viewstate value found on ' . $finderUrl);
        }

        $params = array(
            'formFilialFinderContentTemplate:adresse' => '99099',
            'formFilialFinderContentTemplate:lat' => '50.9629108',
            'formFilialFinderContentTemplate:lng' => '11.074406299999964',
            'formFilialFinderContentTemplate:umkreis' => '100',
            'formFilialFinderContentTemplate:j_id_h4' => '',
            'formFilialFinderContentTemplate_SUBMIT' => '1',
            'javax.faces.ViewState' => $viewStateMatch[1],
            'formFilialFinderContentTemplate:_idcl:formFilialFinderContentTemplate' => 'btnSearchStores'
        );        
        
        Zend_Debug::dump($params);
        
        $sPage->open($finderUrl, $params);            
        $page = $sPage->getPage()->getResponseBody();

        Zend_Debug::dump($page);

        if (!preg_match_all('#<div[^>]*class="filialSucheFiliale"[^>]*>(.+?)<br[^>]*class="clear"[^>]*>\s*</div>#', $page, $storeBlocks)){
            $this->_logger->warn(' no stores found on ' . $singleUrl);
        }        
        
        exit;
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}