<?php

/**
 * Storecrawler für Coma (ID: 68934)
 */
class Crawler_Company_Coma_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $sPage = new Marktjagd_Service_Input_Page();

        $domain = 'http://www.comamaerkte.de';
        $baseUrl = $domain . '/index.php?page=5';
        $searchUrl = $domain . '/index.php?page=5&search_plz=[ZIP]&search_ort=&button_filiale=suchen';

        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        if (!$sPage->open($baseUrl)) {
            throw new Exception('unable to get requested page for company with id ' . $companyId);
        }

        // PLZs aus der Übersichtsseite ermitteln
        $page = $sPage->getPage()->getResponseBody();
        if (!preg_match('#<div[^>]*id="standortkarte"[^>]*>(.+?)</div>#', $page, $matchMap)){
            throw new Exception('unable to get map for company with id ' . $companyId);
        }

        if (!preg_match_all('#<span[^>]*class="title"[^>]*>(.+?)</span>#', $matchMap[1], $matchTitle)){
            throw new Exception('unable to get titles for company with id ' . $companyId);
        }

        foreach ($matchTitle[1] as $zipcodeTitle) {
            $zipcode = $sAddress->extractAddressPart('zipcode', $zipcodeTitle);

            $sRequestLink = preg_replace('#\[ZIP\]#', $zipcode, $searchUrl);

            $this->_logger->log('open ' . $sRequestLink, Zend_Log::INFO);
            if (!$sPage->open($sRequestLink)) {
                $this->_logger->log('unable to get requested page for company with id ' . $companyId, Zend_Log::ERR);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match_all('#<h3>(.+?)</h3>.*?<div[^>]*>.*?<img[^>]*src="([^"]+)"[^>]*>.*?</div>.*?<div[^>]*>(.+?)</div>#si', $page, $match)){
                $this->_logger->log('cannot find any stores at page ' . $sRequestLink, Zend_Log::INFO);
            }

            foreach ($match[0] as $idx => $matchString){
                $eStore = new Marktjagd_Entity_Api_Store();

                $eStore->setImage($domain . '/' . $match[2][$idx]);

                $address = preg_split('#<br[^>]*>#', $match[3][$idx]);
                $eStore->setStreet($sAddress->extractAddressPart('street', $address[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('street_number', $address[0]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $address[1]))
                    ->setCity($sAddress->extractAddressPart('city', $address[1]))
                    ->setPhone($sAddress->normalizePhoneNumber($address[3]))
                    ->setFax($sAddress->normalizePhoneNumber($address[3]));

                // Öffnungszeiten
                //"Geöffnet von 7.00 bis 20.00 UhrSonntags Backshop geöffnet von 8 - 11 Uhr
                if (preg_match('#^[^0-9]*von\s(.+?)\s(bis|-)\s(.+?)\s#i', $address[5], $matchHours)){
                    $eStore->setStoreHours('Mo-Sa ' . $matchHours[1] . '-' . $matchHours[3]);
                }

                if (preg_match('#(sonntags.+$)#i', $address[5], $matchHours)){
                    $eStore->setStoreHoursNotes($matchHours[1]);
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}