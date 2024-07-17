<?php

/*
 * Store Crawler für RWZ (ID: 71708)
 */

class Crawler_Company_Rwz_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.rwz.de/';
        $searchUrl = $baseUrl . 'standorte.html?type=1249058992&tx_storelocator_ajax[uid]=&'
                . 'tx_storelocator_ajax[lat]=50&tx_storelocator_ajax[lng]=10&'
                . 'tx_storelocator_ajax[radius]=1000000&tx_storelocator_ajax[activity]=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $aDists = array(
            '86' => 'Agrar',
            '89' => 'Baustoffe',
            '92' => 'Energie',
            '87' => 'Märkte',
            '93' => 'Technik',
            '85' => 'Weinbau- / Kellerei'
        );
        
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aDists as $distKey => $distName) {
            $sPage->open($searchUrl . $distKey);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#class="link\s*linkToDetailPage"[^>]*href="\/([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                $this->_logger->err($companyId . ': unable to get any store links for dist: ' . $distName);
                continue;
            }
            
            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $storeDetailUrl = $baseUrl . $singleStoreUrl;
                
                $sPage->open($storeDetailUrl);
                $page = $sPage->getPage()->getResponseBody();
                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#itemprop="([^"]+?)"[^>]*>\s*(.+?)\s*<#';
                if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeDetailUrl);
                    continue;
                }
                $aData = array_combine($storeInfoMatches[1], $storeInfoMatches[2]);
            
                $pattern = '#ffnungszeiten(.+?)</dl#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
                }

                $pattern = '#class="storeHeadline"[^>]*>\s*sortiment(.+?)</ul#is';
                if (preg_match($pattern, $page, $sectionListMatch)) {
                    $pattern = '#<img[^>]*>\s*(.+?)\s*</li#is';
                    if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                        $eStore->setSection(preg_replace('#-\s+#', '', implode(', ', $sectionMatches[1])));
                    }
                }
                
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aData['streetAddress'])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aData['streetAddress'])))
                        ->setCity($aData['addressLocality'])
                        ->setZipcode($aData['postalCode'])
                        ->setPhone($sAddress->normalizePhoneNumber($aData['telephone']))
                        ->setFax($sAddress->normalizePhoneNumber($aData['faxNumber']))
                        ->setEmail($sAddress->normalizeEmail($aData['email']))
                        ->setDistribution($distName);
                
                $cStores->addElement($eStore, true);

                if (array_key_exists($eStore->getHash(true), $aStores) && !preg_match('#' . $eStore->getDistribution() . '#i', $aStores[$eStore->getHash(true)])) {
                    $cStores->removeElement($eStore->getHash(true));
                    $eStore->setDistribution($aStores[$eStore->getHash(true)] . ', ' . $eStore->getDistribution());
                    $cStores->addElement($eStore);
                }
                $aStores[$eStore->getHash(true)] = $eStore->getDistribution();
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
