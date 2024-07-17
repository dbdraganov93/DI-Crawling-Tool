<?php

/*
 * Store Crawler für Scharenberg Online (ID: 71716)
 */

class Crawler_Company_Scharenberg_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.juweliere.de/';
        $searchUrl = $baseUrl . 'juweliere';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h1 class="csc-header[^>]*>\s*<a[^>]*href[^>]*>(.+?)<.+?portrait-body[^>]*>(.+?)<[^>]*portrait-footer#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < count($storeMatches[0]); $i++) {
            $pattern = '#<p[^>]*>\s*([^<]+?)\s*\|\s*([0-9]{5}.+?)\s*<br[^>]*>\s*<b[^>]*>(.+?)</b>#';
            if (!preg_match_all($pattern, $storeMatches[2][$i], $addressMatch)) {
                continue;
            }
            for ($j = 0; $j < count($addressMatch[0]); $j++) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setTitle($storeMatches[1][$i])
                        ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $addressMatch[1][$j])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $addressMatch[1][$j])))
                        ->setCity(preg_replace('#([0-9]{5}\s+)#', '',$sAddress->extractAddressPart('city', $addressMatch[2][$j])))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $addressMatch[2][$j]))
                        ->setStoreHours($sTimes->generateMjOpenings($addressMatch[3][$j]));
                                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
