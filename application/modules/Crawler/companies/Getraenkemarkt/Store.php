<?php

/**
 * Storecrawler für Logo Getränkemarkt (ID: 24946)
 */
class Crawler_Company_Getraenkemarkt_Store extends Crawler_Generic_Company
{
    protected $_baseUrl = 'http://www.logo-getraenke.de/logo-getraenke/logo-in-ihrer-naehe/index.php';

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($this->_baseUrl)) {
            throw new Exception('unable to get store-list for company with id ' . $companyId);
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="infowindow"[^>]*>(.+?)</div>#i';
        if (!preg_match_all($pattern, $page, $aStoreMatches)) {
            throw new Exception('unable to get stores for company with id ' . $companyId);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sHours = new Marktjagd_Service_Text_Times();

        foreach ($aStoreMatches[1] as $aStoreMatch) {
            $eStore = new Marktjagd_Entity_Api_Store();

            // Ort
            if (preg_match('#<span[^>]*class="row_1"[^>]*>(.+?)</span>#', $aStoreMatch, $match)){
                $tCity = trim($match[1]);
                // römische Zahlen am Ende entfernen
                $tCity = preg_replace('#[^I]I+$#', '', $tCity);
                $eStore->setCity($tCity);
            }

            // PLZ, Strasse und Hausnummer
            if (preg_match('#<span[^>]*class="row_2"[^>]*>([0-9]{5})\s*(.+?)</span>#', $aStoreMatch, $match)){
                $eStore->setZipcode(trim($match[1]));
                $eStore->setStreet($sAddress->extractAddressPart('street', trim($match[2])))
                        ->setStreetNumber($sAddress->extractAddressPart('street_number', trim($match[2])));
            }

            // Telefonnummer
            if (preg_match('#<span[^>]*class="row_3"[^>]*>(.+?)</span>#', $aStoreMatch, $match)){
                $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
            }

            // Öffnungszeiten
            if (preg_match('#<span[^>]*class="row_5"[^>]*>.+?</span>[^<]*<span[^>]*class="row_6"[^>]*>.+?</span>#', $aStoreMatch, $match)){
                $eStore->setStoreHours($sHours->generateMjOpenings($match[0]));
            }

            $cStores->addElement($eStore, true);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
