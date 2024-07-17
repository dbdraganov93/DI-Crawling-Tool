<?php

/**
 * Store Crawler für Nordhessischer Baustoffmarkt (ID: 71310)
 */
class Crawler_Company_NordhessischerBaustoffmarkt_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.baustoffmarkt-gruppe.de/';
        $searchUrl = $baseUrl . 'standorte';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $site = 1;

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/standorte\?site=([0-9])"[^>]*>End#';
        if (!preg_match($pattern, $page, $lastPageMatch)) {
            throw new Exception($companyId . ': unable to find last page.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($site; $site <= $lastPageMatch[1]; $site++) {
            if (!$sPage->open($searchUrl . '?site=' . $site)) {
                throw new Exception($companyId . ': unable to open store list page.');
            }

            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="spEntriesListCell"[^>]*>(.+?)<div[^>]*class="spclear"[^>]*>\s*</div>\s*</div>#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                throw new Exception($companyId . ': unable to find any stores on site: ' . $searchUrl . '?site=' . $site);
            }
            
            foreach ($storeMatches[1] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#<span[^>]*class="spEntriesListTitle"[^>]*>\s*<a[^>]*href="\/(standorte/([0-9]+)-[^"]+?)"#';
                if (preg_match($pattern, $singleStore, $urlMatch)) {
                    $eStore->setWebsite($baseUrl . $urlMatch[1]);
                }
                
                $pattern = '#field\_street"[^>]*>(.+?)<.+field\_postcode"[^>]*>(.+?)<.+'
                        . 'field\_city"[^>]*>(.+?)<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address.');
                    continue;
                }
                
                $pattern = '#Telefon(.+?)</div#';
                if (preg_match($pattern, $singleStore, $telMatch)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($telMatch[1]));
                }
                
                $pattern = '#Öffnungszeiten\:(.+?)<#';
                if (preg_match($pattern, $singleStore, $storeHourMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHourMatch[1]));
                }
                
                $eStore->setStoreNumber($urlMatch[2])
                        ->setStreet($sAddress->extractAddressPart('street', $addressMatch[1]))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[1]))
                        ->setZipcode($addressMatch[2])
                        ->setCity($addressMatch[3]);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
