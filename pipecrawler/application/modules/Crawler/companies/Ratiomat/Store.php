<?php

/**
 * Store Crawler für Ratiomat Einbauküchen (ID: 69383)
 */
class Crawler_Company_Ratiomat_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ratiomat.de/';
        $searchUrl = $baseUrl . 'studio/werkstudio/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<section[^>]*>(.+?)</section>#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="(http:\/\/www\.ratiomat\.de\/studios\/.+?)"[^>]*>#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreLink) {

            if (!$sPage->open($singleStoreLink)) {
                throw new Exception($companyId . ': unable to open store detail page. url: ' . $singleStoreLink);
            }

            $page = $sPage->getPage()->getResponseBody();
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $pattern = '#<div[^>]*id="merchent_studio"[^>]*>(.+?)</div>\s*</div>#s';
            if (!preg_match($pattern, $page, $detailMatch)) {
                $this->_logger->err($companyId . ': unable to get store details. url: ' . $singleStoreLink);
                continue;
            }
            
            $pattern = '#</span>\s*<br[^>]*>\s*(.+)#';
            if (!preg_match($pattern, $detailMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address. url: ' . $singleStoreLink);
                continue;
            }
            
            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
            
            $pattern = '#<div[^>]*id="opening"[^>]*>(.+?)</div#s';
            if (preg_match($pattern, $page, $storeHourMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHourMatch[1]));
            }
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setPhone($sAddress->normalizePhoneNumber(preg_replace('#\/.+#', '', $aAddress[2])))
                    ->setFax($sAddress->normalizePhoneNumber($aAddress[3]))
                    ->setWebsite($singleStoreLink);
            
            $pattern = '#<ul[^>]*class="bxslider"[^>]*>\s*<li[^>]*>\s*<img[^>]*src="([^<^"]+?)"#';
            if (preg_match($pattern, $detailMatch[1], $imageMatch)) {
                $eStore->setImage($imageMatch[1]);
            }
            
            $pattern = '#LatLng\((.+?),(.+?)\)#';
            if (preg_match($pattern, $page, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                        ->setLongitude($geoMatch[2]);
            }

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
