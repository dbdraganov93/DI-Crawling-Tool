<?php

/**
 * * Storecrawler für Amplifon

 *
 * Class Crawler_Company_Amplifon_Store
 */
class Crawler_Company_Amplifon_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $storeFinderUrl = 'http://www.amplifon.de/Hoertest-buchen/Terminvereinbarung?filiale=';
        $patternStore = '#<span[^>]*id="buchung_right_span"[^>]*>(.*?)</span>#is';
        $errorCount = 0;
        $fillialCount = 0;

        $servicePage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sOpenings = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();

        while ($errorCount <= 20) {
            $storeUrl = $storeFinderUrl . ++$fillialCount;
            $servicePage->open($storeUrl);
            $this->_logger->log('url: ' . $storeUrl, Zend_Log::INFO);
            $sPage = $servicePage->getPage()->getResponseBody();



            if (preg_match($patternStore, $sPage, $matchStore)) {
                if (preg_match('#noch\s*keine\s*Filiale#s', $matchStore[1])) {
                    $errorCount++;
                    continue;
                }

                $errorCount = 0;
                $aStoreInfos = preg_split('#<br\s*\/*>#', $matchStore[1]);
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setSubtitle(trim($aStoreInfos[0]))
                       ->setStreet($sAddress->extractAddressPart('street', $aStoreInfos[1]))
                       ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aStoreInfos[1]))
                       ->setZipcode($sAddress->extractAddressPart('zipcode', $aStoreInfos[2]))
                       ->setCity($sAddress->extractAddressPart('city', $aStoreInfos[2]));

                $openingMerge = $aStoreInfos[4] . ' ' . $aStoreInfos[5];
                if (strlen($aStoreInfos[6])) {
                    $openingMerge .= ', ' . $aStoreInfos[6] . ' ' . $aStoreInfos[7];
                }


                if ($fillialCount != 194) {
                    $eStore->setStoreHours($sOpenings->generateMjOpenings($openingMerge));
                } else {
                    $eStore->setStoreHoursNotes($openingMerge);
                }

                $eStore->setWebsite($storeUrl);
                $eStore->setStoreNumber($fillialCount);


                $cStore->addElement($eStore);
            } else {
                $errorCount++;
                continue;
           }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}