<?php

/* 
 * Store Crawler für AktivSchuh (ID: 67877)
 */

class Crawler_Company_AktivSchuh_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.aktiv-schuh.de/';
        $searchUrl = $baseUrl . 'index.php?id=30';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();
        $oPage = $sPage->getPage()->setMethod('POST');
        $sPage->setPage($oPage);

        $params = array(
            'tx_browser_pi1[radius]' => '1000',
            'tx_browser_pi1[tx_nbstorebrand_brands.title]' => '0',
            'no_cache' => '1',
            'tx_browser_pi1[plugin]' => '',
            'tx_browser_pi1[radialsearch]' => '01309',
            'tx_browser_pi1[tx_nbstorebrand_brands.title][]' => '0'
        );

        $sPage->open($searchUrl, $params);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="(index\.php\?id=30\&tx_browser_pi1%5BshowUid%5D=[^&]+&cHash=[^"]{10})"#s';
        if (!preg_match_all($pattern, $page, $storeListMatch)) {
            throw new Exception ($companyId . ': unable to get store url list: ' . $searchUrl);
        }

        foreach($storeListMatch[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            
            $this->_logger->info($companyId . ': opening ' . $storeDetailUrl);
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<address>.+?<br[^>]*>\s*([^<]+)\s*<br[^>]*>\s*([^<]+)\s*<br[^>]*>\s*Telefon\s*\:\s*([^<]+)\s*</address>#s';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten(.+?)</div#';
            if (preg_match($pattern, $page, $timeMatch)) {
                $eStore->setStoreHoursNormalized($timeMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($addressMatch[1])
                    ->setZipcodeAndCity($addressMatch[2])
                    ->setPhoneNormalized(preg_replace('#\.#', '', $addressMatch[3]));
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId, false);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}