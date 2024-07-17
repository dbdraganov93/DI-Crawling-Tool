<?php

/**
 * Store Crawler für Sport Fundgrube (ID: 29149)
 */
class Crawler_Company_SportFundgrube_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.sport-fundgrube.com/';
        $searchUrl = $baseUrl . 'filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#filialen\s*deutschland(.+?)</ul#is';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="([^"]+?)"\s*title#s';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any store links.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleLink) {
            $storeDetailLink = $baseUrl . $singleLink;
            $sPage->open($storeDetailLink);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#anschrift(.+?)</p#i';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailLink);
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
            foreach ($aAddress as &$singleAddressField) {
                $singleAddressField = strip_tags($singleAddressField);
            }
            
            $pattern = '#lage.+?<td[^>]*>\s*(.+?)\s*<#si';
            if (preg_match($pattern, $page, $subTitleMatch)) {
                $eStore->setSubtitle($subTitleMatch[1]);
            }
            
            $pattern = '#telefon.+?<td[^>]*>\s*(.+?)\s*<#si';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }
            
            $pattern = '#zeiten(.+?)\s*</tbody#si';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $pattern = '#<td[^>]*>\s*(.+?)\s*</td>\s*<td[^>]*>\s*(.+?)\s*</td>#si';
                if (!preg_match_all($pattern, $storeHoursMatch[1], $storeHoursMatches)) {
                    $this->_logger->err($companyId . ': unable to get any store hours: ' . $storeDetailLink);
                    continue;
                }
            }
            
            $pattern = '#<img\s*class="flexible"\s*src="([^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }
            
            $strTimes = '';
            for ($i = 0; $i < count($storeHoursMatches[0]); $i++) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= preg_replace('#\s*bis\s*#', '-', $storeHoursMatches[1][$i]) . ' ' . $storeHoursMatches[2][$i];
            }
            
            $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[2]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[3]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[3]))
                    ->setStoreHours($sTimes->generateMjOpenings($strTimes))
                    ->setStoreNumber($eStore->getHash());

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
