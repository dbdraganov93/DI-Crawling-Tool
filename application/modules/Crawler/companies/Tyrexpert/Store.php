<?php

/**
 * Storecrawler für Tyrexpert (ID: 69975)
 *
 * Class Crawler_Company_Tyrexpert_Store
 */
class Crawler_Company_Tyrexpert_Store extends Crawler_Generic_Company
{

    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     *
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.tyrexpert.de';
        $storeFinderUrl = $baseUrl . '/standorte.html';

        $servicePage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sOpenings = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();

        $servicePage->open($storeFinderUrl);
        $sPage = $servicePage->getPage()->getResponseBody();

        if (!preg_match_all('#<a[^>]*href="(/standorte/detail/([^\.]*)\.html)"[^>]*>#', $sPage, $matchStoreUrls)) {
            throw new Exception('Storecrawler für Tyrexpert (ID: 69975): Konnte Standort-Detail-Urls nicht ermitteln.');
        }

        foreach ($matchStoreUrls[1] as $key => $storeUrl) {
            $servicePage->open($baseUrl . $storeUrl);
            $this->_logger->log('url: ' . $baseUrl . $storeUrl, Zend_Log::INFO);
            $sPage = $servicePage->getPage()->getResponseBody();

            // Filiale aufnehmen
            $eStore = new Marktjagd_Entity_Api_Store();

            // Standortnummer = Index in Url
            $eStore->setStoreNumber(substr($matchStoreUrls[2][$key], 0, 32));

            // Webseite
            $eStore->setWebsite($baseUrl . $storeUrl);

            // Bilder
            if (preg_match_all('#<img[^>]*src="([^"]+tyrexpert-filiale[^"]+)"[^>]*class="[^"]*filialfinder_image[^"]*"#', $sPage, $matchImage)) {
                $eStore->setImage(implode(',', $matchImage[1]));
            }

            // Leistungen
            if (preg_match('#<ul[^>]*class="piktogramme[^"]*"[^>]*>(.+?)</ul>#s', $sPage, $match)) {
                if (preg_match_all('#<li[^>]*title="([^"]+)"[^>]*class="tooltiplink\s*[A-Z]+\_TAG\s+activ"#', $match[1], $match))
                    $eStore->setService(implode(', ', $match[1]));
            }

            // Adresse
            if (preg_match('#<h3>.*?Kontaktdaten.*?</h3>(.+?)<script#s', $sPage, $matchAdr)) {
                $adrLines = preg_split('#<br[^>]*>#', $matchAdr[1]);

                $eStore->setStreet($sAddress->extractAddressPart('street', trim($adrLines[0])));
                $eStore->setStreetNumber($sAddress->extractAddressPart('street_number', trim($adrLines[0])));
                $eStore->setCity($sAddress->extractAddressPart('city', trim($adrLines[1])));
                $eStore->setZipcode($sAddress->extractAddressPart('zipcode', trim($adrLines[1])));

                $eStore->setPhone($sAddress->normalizePhoneNumber(strip_tags($adrLines[2])));
                $eStore->setFax($sAddress->normalizePhoneNumber(strip_tags($adrLines[3])));
            }

            // Öffnungszeiten
            if (preg_match('#<div[^>]*class="[^"]*oeffnungzeiten[^"]*">(.*?)</div>#s', $sPage, $matchOpen)) {
                $eStore->setStoreHours($sOpenings->generateMjOpenings($matchOpen[1]));
            }

            $cStore->addElement($eStore, true);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
