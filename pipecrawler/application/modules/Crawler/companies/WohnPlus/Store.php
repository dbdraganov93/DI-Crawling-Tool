<?php

/* 
 * Store Crawler für Wohn Plus (ID: 71813)
 */class Crawler_Company_WohnPlus_Store extends Crawler_Generic_Company {
    public function crawl($companyId) {
        $searchUrl = 'http://wohnplus.europa-moebel.de/filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($baseUrl . $searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<article\s*class=\"contentBox\">(.+?)<\/article#i';
        if (!preg_match_all($pattern, $page, $aSectionMatch)) {
            throw new Exception('Company ID ' . $companyId . ': could not get store information from ' . $searchUrl);
        }

        $tmp1 = array();
        $tmp2 = array();
        foreach ($aSectionMatch[1] as $tmp1) {
            $pattern = array('#<\/*t\w[^>]*>#', '#<\/*p[^>]*>#', '#<\/*a[^>]*>#', '#<div[^>]*>#');
            $tmp1 = preg_split('#<\/div>#', preg_replace($pattern, '', $tmp1));
            foreach ($tmp1 as $aSingleLine) {
                $tmp2[] = $aSingleLine;
            }
        }

        $offset = 0;
        $length = 0;
        $aStoreData = array();
        foreach ($tmp2 as $key => $sSingleLine) {
            if (preg_match('#.+?mail.+?#i', $sSingleLine)) {
                $length = ($key + 1) - $offset;
                $aStoreData[] = array_slice($tmp2, $offset, $length);
                $offset = $key + 1;
            }
        }

        #Zend_Debug::dump($aStoreData);
        foreach ($aStoreData as $aSingleStoreData) {
            $bGotPhone = false;
            #Zend_Debug::dump($aSingleStoreData);
            $eStore = new Marktjagd_Entity_Api_Store();
            foreach ($aSingleStoreData as $key => $sSingleLine) {
                $pattern = '#\d{5}\s#';
                if (preg_match($pattern, $sSingleLine)) {
                    $eStore->setZipcode($sAddress->extractAddressPart('zipcode', $sSingleLine))
                            ->setCity($sAddress->extractAddressPart('city', $sSingleLine))
                            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aSingleStoreData[$key-1])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aSingleStoreData[$key-1])));
                }

                $pattern = '#Tel[\.|\s|\:]#i';
                if (preg_match($pattern, $sSingleLine) && !$bGotPhone) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($sSingleLine));
                    $bGotPhone = true;
                }
                                $pattern = '#Fax[\.|\s|\:]#i';
                if (preg_match($pattern, $sSingleLine)) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($sSingleLine));
                }

                $pattern = '#([\d\w]+?@[^\"]+\.\w{2,3})#i';
                if (preg_match($pattern, $sSingleLine, $aEmailMatch)) {
                    $eStore->setEmail($aEmailMatch[1]);
                }

                $pattern = '#ffnungszeiten#i';
                if (preg_match($pattern, $sSingleLine, $aStoreHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings($sSingleLine));
                }
            }
            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
 }
