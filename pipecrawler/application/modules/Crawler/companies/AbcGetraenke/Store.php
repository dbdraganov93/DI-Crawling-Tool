<?php

/*
 * Store Crawler für ABC Getränke (ID: 69542)
 */

class Crawler_Company_AbcGetraenke_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://abc-getraenke.de/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\s*([^"]+?)"[^>]*>[^<]+?Markt</a#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div[^>]*class="cycloneslider-slide[^>]*cycloneslider-slide-image"[^>]*>\s*<img[^>]*src="([^"]+?)"[^>]*>.+?</h1>(.+?)</div>\s*</div>#is';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get store info panel: ' . $singleStoreUrl);
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#>([^<]+?)(<[^>]*>\s*)+(\d{5}[^<]+?)<#';
            if (!preg_match($pattern, $storeInfoMatch[2], $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }
            
            $pattern = '#Telefon:?([^<]+?)<#';
            if (preg_match($pattern, $storeInfoMatch[2], $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }
            
            $pattern = '#Telefax:?([^<]+?)<#';
            if (preg_match($pattern, $storeInfoMatch[2], $storeFaxMatch)) {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }
            
            $pattern = '#ffnungszeiten:?(.+?)</p#';
            if (preg_match($pattern, $storeInfoMatch[2], $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#abbr[^>]*title=\'([^\']+?)\'#';
            if (preg_match_all($pattern, $page, $serviceMatches)) {
                for ($i = 0; $i < count($serviceMatches[1]); $i++) {
                    if (preg_match('#Parkplatz#', $serviceMatches[1][$i])) {
                        $eStore->setParking('vorhanden');
                        unset($serviceMatches[1][$i]);
                        break;
                    }
                }
                $eStore->setService(implode(', ', $serviceMatches[1]));
            }
            
            $eStore->setAddress($storeAddressMatch[1], $storeAddressMatch[3]);
            
            if (preg_match('#Bad Salzig#', $eStore->getCity())) {
                $eStore->setZipcode('56154');
            }
            
            if (preg_match('#56332#', $eStore->getZipcode())) {
                $eStore->setStreetAndStreetNumber('Moselstraße 7');
            }
            
            if (!preg_match('#Platzhalter#', $storeInfoMatch[1])) {
                $eStore->setImage($storeInfoMatch[1]);
            }
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
