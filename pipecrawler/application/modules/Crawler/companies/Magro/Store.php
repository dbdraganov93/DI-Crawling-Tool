<?php

/**
 * Storecrawler für Magro (ID: 69989)
 */
class Crawler_Company_Magro_Store extends Crawler_Generic_Company {

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $baseUrl = 'http://magro-uchte.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($baseUrl)) {
            throw new Exception($companyId . ': unable to open page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#Filialen.*?</a>\s*<ul[^>]*>\s*(.+?)\s*</ul>#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="(.+?)"[^>]*>#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();
        $mjTimes = new Marktjagd_Service_Text_Times();

        foreach ($storeUrlMatches[1] as $storeUrlMatch) {
            if (!$sPage->open($storeUrlMatch)) {
                $logger->log($companyId . ': unable to open store-detail-page for url '
                        . $storeUrlMatch, Zend_Log::ERR);
                continue;
            }
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<header[^>]*>\s*<h[^>]*>\s*(.+?)\s*<#';
            if (preg_match($pattern, $page, $titleMatch)) {
                $eStore->setTitle($titleMatch[1]);
            }

            $pattern = '#<div[^>]*class="entry-summary"[^>]*>\s*(.+?)\s*<p[^>]*>#';
            if (!preg_match($pattern, $page, $infoMatch)) {
                $logger->log($companyId . ': unable to get store infos at url '
                        . $storeUrlMatch, Zend_Log::ERR);
                continue;
            }


            $pattern = '#<h4[^>]*>\s*(.+?)\s*</h4>#';
            if (!preg_match_all($pattern, $infoMatch[1], $detailMatches)) {
                $logger->log($companyId . ': unable to get store-details at url '
                        . $storeUrlMatch, Zend_Log::ERR);
                continue;
            }
            $sTimes = $detailMatches[1][4] . ', ' . $detailMatches[1][5];

            $eStore->setStreet($mjAddress->extractAddressPart('street', $detailMatches[1][0]))
                    ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $detailMatches[1][0]))
                    ->setCity($mjAddress->extractAddressPart('city', $detailMatches[1][1]))
                    ->setZipcode($mjAddress->extractAddressPart('zip', $detailMatches[1][1]))
                    ->setPhone($mjAddress->normalizePhoneNumber($detailMatches[1][2]))
                    ->setStoreHours($mjTimes->generateMjOpenings($sTimes));

            $eStore->setStoreNumber(substr(
                            md5(
                                    $eStore->getZipcode()
                                    . $eStore->getCity()
                                    . $eStore->getStreet()
                                    . $eStore->getStreetNumber()
                            )
                            , 0, 25));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
