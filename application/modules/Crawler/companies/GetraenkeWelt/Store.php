<?php

/**
 * Store Crawler für Getränkewelt (ID: 67478)
 */
class Crawler_Company_GetraenkeWelt_Store extends Crawler_Generic_Company {

    protected $_baseUrl = 'http://unternehmen.getraenkewelt.de/index.php?id=232&tx_dgdstores_storelist[address]=';

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($this->_baseUrl)) {
            $logger->log('unable to get store list from ' . $companyId, Zend_Log::CRIT);
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="[^"]*search-marktfinder-result[^"]*"[^>]*>\s*(.+?)\s*<div class="clear">\s*</div>\s*</div>#';
        preg_match_all($pattern, $page, $aStores);

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aStores[1] as $sStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            // Store-Adresse
            $mjAddress = new Marktjagd_Service_Text_Address();
            $pattern = '#class="street"[^>]*>(.+?)<#';
            if (!preg_match($pattern, $sStore, $match)) {
                $logger->log('unable to get store address', Zend_Log::ERR);
                continue;
            }
            $eStore->setStreet($mjAddress->extractAddressPart('street', $match[1]));
            $eStore->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $match[1]));

            $pattern = '#class="zip_city"[^>]*>(.+?)<#';
            if (!preg_match($pattern, $sStore, $match)) {
                $logger->log('unable to get store city address', Zend_Log::ERR);
                continue;
            }
            $eStore->setZipcode($mjAddress->extractAddressPart('zip', $match[1]));
            $eStore->setCity($mjAddress->extractAddressPart('city', $match[1]));

            // Store-Telefon
            $pattern = '#Tel\.\:\s(.+?)<#';
            if (preg_match($pattern, $sStore, $match)) {
                $eStore->setPhone($mjAddress->normalizePhoneNumber($match[1]));
            }

            // Store-Hours
            $mjTimes = new Marktjagd_Service_Text_Times();
            $pattern = '#class="businesshours"[^>]*>\s*<dt[^>]*>\s*(.+?)\s*</dt>#';
            if (!preg_match($pattern, $sStore, $match)) {
                $logger->log('unable to get store hours', Zend_Log::WARN);
            }
            $eStore->setStoreHours($mjTimes->generateMjOpenings($match[1]));

            // Store-Text
            $pattern = '#<li[^>]*>\s*<img[^>]*alt="(.+?)"#';
            if (!preg_match_all($pattern, $sStore, $aTextMatches)) {
                $logger->log('unable to get store description', Zend_Log::WARN);
            } else {
                $strPayment = '';
                $strService = '';
                foreach ($aTextMatches[1] as $sTextMatch) {
                    if (preg_match('#Kundenparkplätze#', $sTextMatch)) {
                        $eStore->setParking('vorhanden');
                        continue;
                    }
                    if (preg_match('#EC\-Karte#', $sTextMatch)) {
                        if (strlen($strPayment)) {
                            $strPayment .= ', ';
                        }
                        $strPayment .= 'EC-Karte';
                        continue;
                    }
                    if (preg_match('#Kommissionsbasis#', $sTextMatch)) {
                        if (strlen($strPayment)) {
                            $strPayment .= ', ';
                        }
                        $strPayment .= 'Kommissionskauf möglich';
                        continue;
                    }
                    if (strlen($strService)) {
                        $strService .= ', ';
                    }
                    $strService .= $sTextMatch;
                }
                $eStore->setPayment($strPayment)
                        ->setService($strService);
            }
            
            

            // Storenummer als Hash
            $eStore->setStoreNumber(substr(
                            md5(
                                    $eStore->getZipcode()
                                    . $eStore->getCity()
                                    . $eStore->getStreet()
                                    . $eStore->getStreetNumber()
                            ), 0, 25));

            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
