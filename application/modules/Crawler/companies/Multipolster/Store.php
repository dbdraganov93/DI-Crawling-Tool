<?php

/**
 * Storecrawler für Multipolster (ID: 69956)
 *
 * Class Crawler_Company_Multipolster_Store
 */
class Crawler_Company_Multipolster_Store extends Crawler_Generic_Company {

    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $baseUrl = 'https://www.multipolster.de';
        $searchUrl = $baseUrl . '/filialen/standorte';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $cStores = new Marktjagd_Collection_Api_Store();

        $aStoreUrls = array();
        $patternStores = '#<area[^>]*href="(.*?)"[^>]*>#';
        if (!preg_match_all($patternStores, $page, $match)) {
            throw new Exception('Multipolster (ID:' . $companyId . '): '
            . 'Konnte area-URLs nicht von Webseite auslesen');
        }

        foreach ($match[1] as $areaUrl) {
            $sPage->open($baseUrl . $areaUrl);
            $page = $sPage->getPage()->getResponseBody();

            if (preg_match_all('#<a[^"]+href="(/filialen/[^/]+/[^"]+)">#', $page, $matchStore)) {
                foreach ($matchStore[1] as $storeUrl) {
                    if (!preg_match('#/([A-Z]+)$#', $storeUrl, $matchStoreNumber)) {
                        throw new Exception('Multipolster (ID:' . $companyId . '): '
                        . 'Konnte Standortnummer aus URL ' . $storeUrl . ' nicht auslesen');
                    }

                    $aStoreUrls[$matchStoreNumber[1]] = $baseUrl . $storeUrl;
                }
            }
        }

        foreach ($aStoreUrls as $storeNumber => $storeUrl) {
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match('#var\s*mapAddress\s*=\s*\'(.*?)\'#', $page, $addressMatch)) {
                $this->_logger->log('Multipolster (ID:' . $companyId . '): '
                        . 'Konnte Adresse für Standort-URL ' . $storeUrl . 'nicht von Webseite auslesen.', Zend_Log::ERR);
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $aAddress = preg_split('#\s*,\s*#', $addressMatch[1]);

            $eStore->setStoreNumber($storeNumber)
                    ->setZipcodeAndCity($aAddress[0])
                    ->setStreetAndStreetNumber($aAddress[1])
                    ->setWebsite($storeUrl);

            $patternOpening = '#<h2>.*?ffnungszeiten</h2>(.+?)<div[^>]*class="row[^>]*no-margins[^>]*3d#is';
            if (preg_match($patternOpening, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $patternTel = '#>\s*Tel\.?\:?\s*([^<]+?)<#is';
            if (preg_match($patternTel, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $patternFax = '#>\s*Fax\:?\s*([^<]+?)<#is';
            if (preg_match($patternFax, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }
            
            $pattern = '#mailto:([^"]+?)"#is';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
