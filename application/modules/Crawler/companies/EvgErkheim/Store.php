<?php

/*
 * Store Crawler für EVG Erkheim (ID: 71490)
 */

class Crawler_Company_EvgErkheim_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.evg-erkheim.de/';
        $searchUrl = $baseUrl . 'index.php?option=com_content&view=article&id=173&Itemid=27';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+)"[^>]*>weitere Informationen\s*</a>#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div[^>]*class="content"[^>]*>(.+?)(<script|<p[^>]*>\s*<span)#s';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->warn($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                continue;
            }

            $aInfos = preg_split('#<br[^>]*>#', preg_replace('#(<img[^>]*>|<[^>]*strong[^>]*>)#', '', $storeInfoMatch[1]));
            $pattern = '#src="\/([^"]+?)"#';
            if (preg_match($pattern, $storeInfoMatch[1], $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }

            $pattern = '#ffnungszeiten(.+?)<table#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }

            $pattern = '#artikelprogramm.+?h4>>\s*(.+?)\s*</td#si';
            if (preg_match($pattern, $page, $sectionMatch)) {
                $eStore->setSection(preg_replace('#\s*<br[^>]*>>\s*#', ', ', $sectionMatch[1]));
            }

            for ($i = 0; $i < count($aInfos); $i++) {
                if (preg_match('#^[0-9]{5}#', $aInfos[$i])) {
                    $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', trim(strip_tags($aInfos[$i - 1])))))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', trim(strip_tags($aInfos[$i - 1])))))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', trim(strip_tags($aInfos[$i]))))
                            ->setCity($sAddress->extractAddressPart('city', trim(strip_tags($aInfos[$i]))));
                }

                if (preg_match('#Tel#', $aInfos[$i])) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($aInfos[$i]));
                }

                if (preg_match('#Fax#', $aInfos[$i])) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($aInfos[$i]));
                }
            }
            
            $eStore->setStoreNumber($eStore->getHash());

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
