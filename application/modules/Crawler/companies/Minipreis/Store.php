<?php

class Crawler_Company_Minipreis_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.minipreis.de/';
        $searchUrl = $baseUrl . 'maerkte/oeffnungszeiten/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sAddress = new Marktjagd_Service_Text_Address();
        
        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to get store list page.');
        }
        
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="listView"[^>]*>(.+?)</div>\s*<div[^>]*id="c159#';
        if (!preg_match($pattern, $page, $listMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<div[^>]*class="locator-img-wrap"[^>]*>(.+?)</form#';
        if (!preg_match_all($pattern, $listMatch[1], $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        
        $cStore = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#<div[^>]*>(.+?)</div#';
            if (!preg_match_all($pattern, $singleStore, $detailMatches)) {
                $this->_logger->log($companyId . ': unable to get any stores.', Zend_Log::ERR);
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStreet($sAddress->extractAddressPart('street', $detailMatches[1][1]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $detailMatches[1][1]))
                    ->setZipcode($sAddress->extractAddressPart('zip', $detailMatches[1][2]))
                    ->setCity($sAddress->extractAddressPart('city', $detailMatches[1][2]))
                    ->setStoreHours($sTimes->generateMjOpenings(preg_replace('#\.\.#', ' ', $detailMatches[1][3])))
                    ->setPhone($sAddress->normalizePhoneNumber($detailMatches[1][5]))
                    ->setStoreNumber($eStore->getHash());
            
            $pattern = '#<img[^>]*src="(.+?)"#';
            if (preg_match($pattern, $singleStore, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }
            
            $pattern = '#to_market_link"[^>]*href="(.+?)"#';
            if (preg_match($pattern, $singleStore, $linkMatch)) {
                $eStore->setWebsite($baseUrl . $linkMatch[1]);
            }

            $cStore->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}