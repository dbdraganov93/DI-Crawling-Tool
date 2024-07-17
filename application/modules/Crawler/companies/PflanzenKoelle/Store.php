<?php

/* 
 * Store Crawler für Pflanzen Kölle (ID: 69974)
 */

class Crawler_Company_PflanzenKoelle_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pflanzen-koelle.de/';
        $searchUrl = $baseUrl . 'StoreSelect/search?input=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $detailUrl = $baseUrl . 'StoreSelect/getStoreDetails?storeID=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'zip', 25);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $aStoreNumbers = array();
        
        foreach ($aUrls as $singleUrl)
        {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#data-rel="([0-9]+?)"#';
            if (!preg_match_all($pattern, $page, $storeNumberMatches))
            {
                $this->_logger->info($companyId . ': no stores found: ' . $singleUrl);
                continue;
            }
            foreach ($storeNumberMatches[1] as $singleStoreNumber)
            {
                if (!in_array($singleStoreNumber, $aStoreNumbers))
                {
                    $aStoreNumbers[] = $singleStoreNumber;
                }
            }
        }
        Zend_Debug::dump($aStoreNumbers);die;
        
    }
}