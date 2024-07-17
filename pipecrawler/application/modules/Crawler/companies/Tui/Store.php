<?php

/**
 * Standortcrawler für TUI Deutschland (ID: 71059)
 * First Reisebüros sind die Fillialen von TUI Deutschland
 *
 * Class Crawler_Company_Tui_Store
 */
class Crawler_Company_Tui_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     *
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $sGenerator = new Marktjagd_Service_Generator_Url();
        $sDb = new Marktjagd_Database_Service_GeoRegion();
        $baseUrl = 'http://www.first-reisebuero.de/';
        $searchUrl = $baseUrl . 'buerosuche?suche=' . $sGenerator::$_PLACEHOLDER_ZIP . '&submitSearch=Jetzt+suchen&returnMethod=&anrede=&vorname=&nachname=&mail=&nl_quelle=';

        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();


        $aUrls = $sGenerator->generateUrl($searchUrl, $sGenerator::$_TYPE_ZIP, 50);

        foreach ($aUrls as $url) {
            $this->_logger->info('open url: ' . $url);
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();
            $qStores = new Zend_Dom_Query($page, 'UTF-8');

            $nStores = $qStores->query("div[class*=\"officeListOffice\"]");
            foreach ($nStores as $nStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $sStore = $nStore->c14n();

                $patternAddress = '#<div\s*data-placeholder="Adresse">\s*<span[^>]*>([^<]+?)<br></br>([^<]+?)</span#is';
                if (!preg_match($patternAddress, $sStore, $matchAddress)) {
                    $this->_logger->info($companyId . ': unable to get store address.');
                    continue;
                }

                $eStore->setStreet($sAddress->extractAddressPart('street', $matchAddress[1]))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $matchAddress[1]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $matchAddress[2]))
                        ->setCity($sDb->findCityByZipCode($eStore->getZipcode()));

                $patternPhone = '#<div[^>]*class="officeListOfficeTelefon"[^>]*>(.*?)</div>#is';
                if (preg_match($patternPhone, $sStore, $matchPhone)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber(strip_tags($matchPhone[1])));
                }

                $patternMail = '#<a[^>]*href="mailto\:(.*?)"[^>]*>#is';
                if (preg_match($patternMail, $sStore, $matchMail)) {
                    $eStore->setEmail($matchMail[1]);
                }

                $patternOpenings = '#<div[^>]*class="fl"[^>]*>(.*?)</div>\s*<div[^>]*class="fr"[^>]*>(.+?)</div>#';
                $sOpening = '';
                if (preg_match_all($patternOpenings, $sStore, $matchOpenings)) {
                    foreach ($matchOpenings[1] as $key => $weekdayRange) {
                        $sOpening .= $weekdayRange . ' ' . $matchOpenings[2][$key];
                    }

                    $eStore->setStoreHours($sTimes->generateMjOpenings($sOpening));
                }

                $patternStoreNr = '#data-agnt="(.*?)"#is';
                if (preg_match($patternStoreNr, $sStore, $matchStoreNr)) {
                    $eStore->setStoreNumber($matchStoreNr[1]);
                }

                $patternWebsite = '#data-agnturl="(.*?)"#is';
                if (preg_match($patternWebsite, $sStore, $matchWebsite)) {
                    $eStore->setWebsite($matchWebsite[1]);
                }

                $eStore->setLogo('http://www.first-reisebuero.de/brands/1/img/logo.png');

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
