<?php

/**
 * Standortcrawler für Aldi CH (ID: 72133)
 */
class Crawler_Company_AldiCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.aldi-suisse.ch/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();

        $aZipcodes = $sDbGeo->findAll('CH');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipcodes as $singleZipcode) {
            $searchUrl = $baseUrl . 'filialen/de-ch/Search?SingleSlotGeo=' . $singleZipcode->getZipcode() . '&Mode=None';
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="[^"]*shopInfos[^>]*>(.+?)</li#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': no stores for ' . $searchUrl);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                if (!preg_match('#<strong[^>]*class="resultItem-CompanyName"[^>]*itemprop="name">\s*ALDI\s*SUISSE#', $singleStore)) {
                    continue;
                }

                $pattern = '#<div[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
                if (!preg_match_all($pattern, $singleStore, $addressMatches)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }

                $aAddress = array_combine($addressMatches[1], $addressMatches[2]);

                $pattern = '#^\d{4}\s+#';
                if (!preg_match($pattern, $aAddress['addressLocality'])) {
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<table[^>]*class="openingHoursTable"[^>]*>(.+?)</table#';
                if (preg_match($pattern, $singleStore, $storeHoursListMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursListMatch[1]);
                }

                $pattern = '#<a[^>]*itemprop="telephone"[^>]*href="tel:([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#img\/filters\/Icon_([^\.]+?)\.svg#';
                if (preg_match_all($pattern, $singleStore, $serviceMatches)) {
                    $strSection = '';
                    foreach ($serviceMatches[1] as $singleService) {
                        if (preg_match('#Parking#', $singleService)) {
                            $eStore->setParking('vorhanden / existant / esistente');
                            continue;
                        }

                        if (preg_match('#Wheelchair#', $singleService)) {
                            $eStore->setBarrierFree(TRUE);
                            continue;
                        }

                        if (preg_match('#Baguette#', $singleService)) {
                            if (strlen($strSection)) {
                                $strSection .= ', ';
                            }
                            $strSection .= 'PANETTERIA';
                            continue;
                        }

                        if (preg_match('#Recycling#', $singleService)) {
                            if (strlen($strSection)) {
                                $strSection .= ', ';
                            }
                            $strSection .= 'Recyclingstation / centre de recyclage / centro di riciclaggio';
                        }
                    }
                    $eStore->setSection($strSection);
                }

                $eStore->setStreetAndStreetNumber($aAddress['streetAddress'], 'CH')
                    ->setZipcodeAndCity($aAddress['addressLocality']);

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

}
