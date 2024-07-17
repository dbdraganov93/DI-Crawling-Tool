<?php

/**
 * Store Crawler für Zooma Zoofachmarkt (ID: 71932)
 */
class Crawler_Company_Zooma_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://zooma.de/';
        $searchUrl = $baseUrl . 'index.php?id=6&type=0&no_cache=1&myplz=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP . '&submit=Suchen...';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aPatternSkipMarket = array(
            '#raiffeisen#i',
            '#kiebitzmarkt#i',
            '#zookauf#i',
        );

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 50);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match_all('#<h3>([^<]+)<br></h3><strong>.*?</strong><br>(.*?)<br>(.*?)<br>(.*?)<br>(.*?)<br>\s*<br>\s*<br>#', $page, $storesMatch)) {
                $this->_logger->err($companyId . ': unable to get stores for url: ' . $singleUrl);
            }

            foreach ($storesMatch[1] as $key => $value) {
                foreach ($aPatternSkipMarket as $skipMarket) {
                    if (preg_match($skipMarket, $storesMatch[1][$key])) {
                        $this->_logger->log($storesMatch[1][$key] . ' wurde übersprungen, da bereits in anderem Unternehmen gelistet', Zend_Log::INFO);
                        continue 2;
                    }

                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStreetAndStreetNumber($storesMatch[2][$key]);
                $eStore->setZipcodeAndCity($storesMatch[3][$key]);
                $eStore->setPhoneNormalized($storesMatch[3][$key]);

                $title = trim(preg_replace('#' . $eStore->getCity() . '$#', '', $storesMatch[1][$key]));
                $eStore->setTitle($title);

                $patternFax = '#Fax\:\s*([^<]*)(<|$)#';
                if (preg_match($patternFax, $storesMatch[5][$key], $matchFax)) {
                    $eStore->setFaxNormalized($matchFax[1]);
                }

                $patternUrl = '#<a\s*href=\"([^\"]+)\"#';
                if (preg_match($patternUrl, $storesMatch[5][$key], $matchUrl)) {
                    $eStore->setWebsite(str_replace('http://http://', 'http://', $matchUrl[1]));
                }

                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}