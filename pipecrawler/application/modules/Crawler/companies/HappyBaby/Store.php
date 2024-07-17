<?php

/* 
 * Store Crawler für Happy Baby (ID: 29014)
 */

class Crawler_Company_HappyBaby_Store extends Crawler_Generic_Company {    
    public function crawl($companyId) {
        $baseUrl = 'http://www.happybaby.de/';
        $searchUrl = $baseUrl . 'happy-baby/haendler/deutschland.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();        

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href=\"\/([^\"]+?)\"[^>]*class\=\"text-deco-none\"#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception ($companyId . ': unable to get any stores from list.');
        }

        foreach ($storeMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            echo 'Open URL: ' . $storeDetailUrl . PHP_EOL;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Kontakt(.+?)</div#';
            if (!preg_match($pattern, $page, $storeContactMatch)) {
                $this->_logger->err($companyId . ': unable to get store contact infos.');
                echo 'YES ' . PHP_EOL;
                continue;
            }

            $aInfos = preg_split('#(\s*<[^>]*>\s*)+#', $storeContactMatch[1]);
            Zend_Debug::dump($aInfos);

            $eStore = new Marktjagd_Entity_Api_Store();
            $pattern = '#ffnungszeiten(.+?)(Heiligabend.+?)(Silvester.+?)</table#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(strip_tags($storeHoursMatch[1])));
                $strStoreHoursNotes = '';
                if (strlen(trim(strip_tags($storeHoursMatch[2]))) > 20) {
                    if (strlen($strStoreHoursNotes)) {
                        $strStoreHoursNotes .= ', ';
                    }
                    $strStoreHoursNotes .= trim(strip_tags($storeHoursMatch[2]));
                }

                if (strlen(trim(strip_tags($storeHoursMatch[3]))) > 20) {
                    if (strlen($strStoreHoursNotes)) {
                        $strStoreHoursNotes .= ', ';
                    }
                    $strStoreHoursNotes .= trim(strip_tags($storeHoursMatch[3]));
                }

                $eStore->setStoreHoursNotes($strStoreHoursNotes);
            }

            $pattern = '#<img[^>]*class="image"[^>]*src="\/([^"]+?)"#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }

            if ($singleStoreUrl === 'aachen.html' || $singleStoreUrl === 'ahrweiler.html') {
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aInfos[3])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aInfos[3])))
                    ->setCity($sAddress->extractAddressPart('city', $aInfos[4]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aInfos[4]))
                    ->setPhone($sAddress->normalizePhoneNumber($aInfos[6]))
                    ->setFax($sAddress->normalizePhoneNumber($aInfos[7]))
                    ->setEmail($aInfos[9] . $aInfos[11] . $aInfos[12] . '.' . $aInfos[15])
                    ->setWebsite($storeDetailUrl);
            } else {
                $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aInfos[2])))
                        ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aInfos[2])))
                        ->setCity($sAddress->extractAddressPart('city', $aInfos[3]))
                        ->setZipcode($sAddress->extractAddressPart('zipcode', $aInfos[3]))
                        ->setPhone($sAddress->normalizePhoneNumber($aInfos[4]))
                        ->setFax($sAddress->normalizePhoneNumber($aInfos[5]))
                        ->setEmail($aInfos[7] . $aInfos[9] . $aInfos[10] . '.' . $aInfos[13])
                        ->setWebsite($storeDetailUrl);
            }

            $cStores->addElement($eStore);
            }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
