<?php

/*
 * Store Crawler für Elements (Bad)ID: 71986)
 */

class Crawler_Company_Elements_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.elements-show.de/';
        $searchUrl = $baseUrl . 'ausstellungssuche/alle-elements-ausstellungen';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<span[^>]*class="field-content"[^>]*>\s*<a[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Kontakt</h2>(.+?)<div[^>]*itemprop#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#<span[^>]*class="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $infoListMatch[1], $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos from list: ' . $storeDetailUrl);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            if (!preg_match('#Deutschland#', $aInfos['country'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten(.+)#';
            if (preg_match($pattern, $infoListMatch[1], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#itemprop="telephone"[^>]*content="([^"]+?)"#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#itemprop="faxNumber"[^>]*content="([^"]+?)"#';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#itemprop="email"[^>]*content="([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($mailMatch[1]);
            }

            $pattern = '#itemprop="(latitude|longitude)"[^>]*content="([^"]+?)"#';
            if (preg_match_all($pattern, $page, $geoMatches)) {
                $aGeo = array_combine($geoMatches[1], $geoMatches[2]);
                $eStore->setLatitude($aGeo['latitude'])
                        ->setLongitude($aGeo['longitude']);
            }
            
            $pattern = '#<div[^>]*class="insider-leistungen-icon-desc"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match_all($pattern, $page, $serviceMatches)) {
                $eStore->setService(implode(', ', $serviceMatches[1]));
            }

            $eStore->setStreet($aInfos['thoroughfare'])
                    ->setStreetNumber($aInfos['premise'])
                    ->setZipcode($aInfos['postal-code'])
                    ->setCity($aInfos['locality']);

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
