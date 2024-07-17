<?php

/**
 * Storecrawler für Bilgro (ID: 67342)
 */
class Crawler_Company_Bilgro_Store extends Crawler_Generic_Company
{
    /**
     * Initiert den Crawling-Prozess
     *
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $baseUrl = 'http://www.bilgro.de';
        $storeListUrl = $baseUrl . '/standorte/';

        $servicePage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $cStore = new Marktjagd_Collection_Api_Store();

        $servicePage->open($storeListUrl);
        $page = $servicePage->getPage()->getResponseBody();

        //<ul id="navi-prim">....</ul>
        $pattern = '#<ul[^>]*id="navi-prim"[^>]*>(.+?)</ul>#';
        if (!preg_match($pattern, $page, $matchUL)){
            //Fehler (Ende)
            throw new Exception('cannot find store list ' . $storeListUrl . ' for company ' . $companyId);
        }

        $pattern = '#<li[^>]*>(.+?)</li>#';
        if (!preg_match_all($pattern, $matchUL[1], $matchLI)){
            //Fehler (Ende)
            throw new Exception('cannot find store list ' . $matchUL . ' for company ' . $companyId);
        }

        foreach ($matchLI[1] as $listLink){
            if (!preg_match('#<a[^>]*href="([^"]+)"[^>]*>#', $listLink, $storesUrl)){
                throw new Exception('cannot find store list-item ' . $matchUL . ' for company ' . $companyId);
            }

            $storesUrl = $baseUrl . '/' . $storesUrl[1];
            $this->_logger->log('open page ' . $storesUrl, Zend_Log::INFO);

            $servicePage->open($storesUrl);
            $page = $servicePage->getPage()->getResponseBody();

            if (!preg_match('#<table[^>]*class="kurstable"[^>]*>(.+?)</table>#', $page, $matchTable)){
                $this->_logger->log('cannot match store table ' . $storesUrl, Zend_Log::ERR);
                continue;
            }

            if (!preg_match_all('#<tr[^>]*>(.+?)</tr>#', $matchTable[1], $matchRow)){
                $this->_logger->log('cannot match store rows ' . $storesUrl, Zend_Log::ERR);
                continue;
            }

            foreach ($matchRow[1] as $tableRow){
                if (!preg_match_all('#<td[^>]*>(.+?)</td>#', $tableRow, $tableCells)){
                    continue;
                }

                // neues Standort-Objekt erzeugen
                $eStore = new Marktjagd_Entity_Api_Store();

                foreach ($tableCells[1] as $idx => $cellData){
                    switch ($idx) {
                        case 0: $eStore->setZipcode(trim($cellData));
                            break;
                        case 1: $eStore->setCity(trim($cellData));
                            break;
                        case 2:
                            // Telefon
                            if (preg_match('#>\s*tel(.+?)<#si', $cellData, $matchPhone)){
                                $eStore->setPhone($sAddress->normalizePhoneNumber($matchPhone[1]));
                                $this->_logger->log('phone ' . $eStore->getPhone(), Zend_Log::INFO);
                            }

                            // Email
                            if (preg_match('#"mailto:([^"]+)"#si', $cellData, $matchMail)){
                                $eStore->setEmail(trim($matchMail[1]));
                                $this->_logger->log('mail ' . $eStore->getEmail(), Zend_Log::INFO);
                            }

                            $lines = preg_split('#<br[^>]*>#', $cellData);
                            foreach($lines as $lineNo => $lineData){
                                switch ($lineNo) {
                                    case 0:
                                        if (preg_match('#[0-9]#', $lineData)){
                                            $eStore->setStreet($sAddress->extractAddressPart('street', $lineData));
                                            $eStore->setStreetNumber($sAddress->extractAddressPart('street_number', $lineData));
                                        } else {
                                            $eStore->setSubtitle(trim($lineData));
                                        }
                                        break;
                                    case 1:
                                        if (preg_match('#[0-9]#', $lineData) && !strlen($eStore->getStreet())){
                                            $eStore->setStreet($sAddress->extractAddressPart('street', $lineData));
                                            $eStore->setStreetNumber($sAddress->extractAddressPart('street_number', $lineData));
                                        } else {
                                            if (!strlen($eStore->getSubtitle())){
                                                $text = trim($lineData);
                                                $text = trim(substr($text, 0, strpos($text, '<')));
                                                $eStore->setText(trim($text));
                                            }
                                        }
                                        break;
                                }
                            }

                            if (!strlen($eStore->getStreet())){
                                $eStore->setStreet($sAddress->extractAddressPart('street', $lines[0]));
                                $eStore->setStreetNumber($sAddress->extractAddressPart('street_number', $lines[0]));
                            }

                            break;
                    }
                }

                // Öffnungszeiten
                $storeHours = '';

                // Uhrzeiten ermitteln
                $hours = preg_split('#<br[^>]*>#', $tableCells[1][4]);

                // Tage ermitteln, ggf. Lücke auffüllen
                $days = preg_split('#<br[^>]*>#', $tableCells[1][3]);
                foreach ($days as $idx => $day){
                    if (!strlen($days[$idx])){
                        $days[$idx] = $days[$idx-1];
                    }

                    if (strlen($hours[$idx])){
                        $storeHours .= $days[$idx] . ' ' . preg_replace('#\´#', '', $hours[$idx]) . ',';
                    }
                }

                $storeHours = substr($storeHours, 0, strlen($storeHours)-1);

                $storeHours = preg_replace('#\s*Biergarten\s*#', '', $storeHours);
                $storeHours = preg_replace('#–#', '-', $storeHours);

                $eStore->setStoreHours($storeHours);

                // doppelter Standort
                if (trim($eStore->getSubtitle()) == 'Mierisch' && $eStore->getCity() == 'Dresden'){
                    continue;
                }

                if (preg_match('#[0-9]#', $eStore->getZipcode())){
                    $cStore->addElement($eStore);
                }
            }
        }

         //$cStore->addElement($eStore, true);
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}