<?php

/**
 * Storecrawler für Ralph Lauren (ID: 69936)
 */
class Crawler_Company_RalphLauren_Store extends Crawler_Generic_Company {

    protected $_baseUrl = 'http://global.ralphlauren.com';

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $searchUrl[] = $this->_baseUrl . '/de-de/rlstores/pages/SearchResults.aspx?'
                . 'lat=50&log=10&rad=2000.00&div=9';
        $searchUrl[] = $this->_baseUrl . '/de-de/rlstores/pages/SearchResults.aspx?'
                . 'lat=50&log=10&rad=2000.00&div=&ab=SLLP_HERO_FINDASTORE_SEARCH';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $aEnglishDays = array(
            '#Mon#',
            '#Tue#',
            '#Wed#',
            '#Thu#',
            '#Fri#',
            '#Sat#',
            '#Sun#'
        );

        $aGermanDays = array(
            'Mo',
            'Di',
            'Mi',
            'Do',
            'Fr',
            'Sa',
            'So'
        );

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($searchUrl as $singleUrl) {
            if (!$sPage->open($singleUrl)) {
                throw new Exception('unable to get store-list from company with id ' . $companyId);
            }

            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<li[^>]*class[^>]*(storeid=.+?)</li>#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#divStoreInfo"[^>]*>.+?<h4[^>]*>(.+?)<br[^>]*>\s*<a#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address.');
                    continue;
                }

                $pattern = '#Germany#';
                if (!preg_match($pattern, strip_tags($addressMatch[1]))) {
                    continue;
                }

                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
                $eStore = new Marktjagd_Entity_Api_Store();

                // Öffnungszeiten
                $aTimes = array();
                $pattern = '#lblStoreHours"[^>]*>(.+?)<#';
                if (preg_match($pattern, $singleStore, $match)) {
                    $aTimes = preg_split('#[\;\,]\s*#', preg_replace($aEnglishDays, $aGermanDays, $match[1]));
                }
                
                for ($i = 0; $i < count($aTimes); $i++) {

                    // 24h - Format
                    $pattern = '#\-\s*([0-9]{1,2})#';
                    preg_match($pattern, $aTimes[$i], $sTimeMatch);
                    if (strlen($sTimeMatch[1])) {
                        if (intval($sTimeMatch[1]) <= 12) {
                            $iTimeReplace = intval($sTimeMatch[1]) + 12;
                            $aTimes[$i] = preg_replace('#\-\s*[0-9]{1,2}#', '-' . $iTimeReplace, $aTimes[$i]);
                        }

                        // Zeiten formatieren
                        $aTimes[$i] = preg_replace('#([a-z])\s*\:*\s*([0-9])#', '$1 $2', $aTimes[$i]);
                        $aTimes[$i] = preg_replace('#\s*\-\s*#', '-', $aTimes[$i]);
                        $aTimes[$i] = $this->splitOpening($aTimes[$i]);
                    }
                }
                
                $sTime = implode(', ', $aTimes);
                $eStore->setStoreHours($sTimes->generateMjOpenings($sTime));

                $pattern = '#href="tel\:[^>]*"[^>]*>(.+?)<#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber(html_entity_decode($phoneMatch[1])));
                }

                $pattern = '#storeid="([0-9]+?)"#';
                if (!preg_match($pattern, $singleStore, $storeNumberMatch)) {
                    $this->_logger->err($companyId . ': unable to get store number.');
                    continue;
                }

                $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress) - 3]))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress) - 3]))
                        ->setCity(preg_replace(array('#Munich#', '#(Duss|Duess)#', '#Koln#', '#(br|um)u(ck|ns)#'), array('München', 'Düss', 'Köln', '$1ü$2'), $sAddress->extractAddressPart('city', $aAddress[count($aAddress) - 2])))
                        ->setZipcode($sAddress->extractAddressPart('zip', $aAddress[count($aAddress) - 2]))
                        ->setStoreNumber($storeNumberMatch[1]);

                if ($eStore->getStoreNumber() == '76115') {
                    $eStore->setZipcode('81675');
                }
                
                $cStores->addElement($eStore, TRUE);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * Funktion, welche ':00' an unvollständige Zeiten hängt
     *
     * @param string $sTime
     * @return string
     */
    protected function addZeros($sTime) {
        $aTimesComplete = explode(' ', $sTime);
        $aTimes = explode('-', $aTimesComplete[1]);
        for ($i = 0; $i < count($aTimes); $i++) {
            if (!preg_match('#(\:|\.)#', $aTimes[$i])) {
                $aTimes[$i] .= ':00';
            }
        }
        $sTime = $aTimesComplete[0] . ' ' . implode('-', $aTimes);
        return $sTime;
    }

    /**
     * Funktion um getrennte Öffnungszeiten anzupassen
     *
     * @param string $sTime
     * @return string
     */
    protected function splitOpening($sTime) {
        $pattern = '#\/#';
        if (!preg_match($pattern, $sTime)) {
            return $this->addZeros($sTime);
        }
        $aDays = explode(' ', $sTime);
        $aTimes = preg_split('#(\/|\s*und\s*)#', $aDays[1]);
        for ($i = 0; $i < count($aTimes); $i++) {
            $aHours = explode('-', $aTimes[$i]);
            if (!preg_match('#(\:|\.)#', $aHours[0])) {
                $aHours[0] .= ':00';
            }
            if (!preg_match('#(\:|\.)#', $aHours[1])) {
                $aHours[1] .= ':00';
            }
            $aTimes[$i] = $aDays[0] . ' ' . implode('-', $aHours);
        }
        $sTime = implode(', ', $aTimes);
        $sTime = preg_replace('#([0-9]{1,2})\.([0-9]{2})\-([0-9]{1,2})\.([0-9]{2})#', '$1:$2-$3:$4', $sTime);
        return $sTime;
    }

}
