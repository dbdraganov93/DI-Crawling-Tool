<?php

/**
 * Store Crawler für Auto Plus (ID: 24945)
 */
class Crawler_Company_AutoPlus_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://autoplus.de/';
        $searchUrl = $baseUrl . 'filialen';
        
        $cStores = new Marktjagd_Collection_Api_Store();                
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a href="(https://autoplus.de/filiale/[^"]*)">([^<]*)</a>#i';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception('unable to get stores: ' . $searchUrl);
        }

        foreach ($storeMatches[0] as $key => $value) {
            $storeUrl = $storeMatches[1][$key];
            $sPage->open($storeUrl);
            $detailPage = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            // adresse
            $pattern = '#>([^<]*)<br[^>]*>\s*([0-9]{5})([^<]*)<#i';
            if (!preg_match($pattern, $detailPage, $match)) {
                $this->_logger->err('unable to get store address: ' . $storeUrl);
                continue;
            }
            $eStore->setStreetAndStreetNumber(trim($match[1]));
            $eStore->setZipcode($match[2]);
            $eStore->setCity(trim($match[3]));

            // Untertitel bzw. abweichenden Store-Titel setzen, siehe #15894
            $pattern = '#<td[^>]*>\s*<b>([^<]*)</b>#i';
            if (preg_match($pattern, $detailPage, $match)) {
                $subtitle = trim($match[1]);
                if ('auto plus' != strtolower($subtitle)) {
                    if (preg_match('#ad\s*-\s*auto\s*dienst#is', $subtitle)) {
                        continue;
                    } else {
                        $eStore->setSubtitle($subtitle);
                    }
                }
            }

            // Telefon & Telefax
            $telPattern = array(
                '#[^0-9]#',
            );
            $telReplacement = array(
                '',
            );
            // Telefon
            $pattern = '#>\s*Tel(efon)?[\.:]*([^<]*)<#i';
            if (preg_match($pattern, $detailPage, $match)) {
                $eStore->setPhone(preg_replace($telPattern, $telReplacement, $match[2]));
            }
            // Telefax
            $pattern = '#fax:?([^<]*)<#i';
            if (preg_match($pattern, $detailPage, $match)) {
                $eStore->setFax(preg_replace($telPattern, $telReplacement, $match[1]));
            }

            if (preg_match('#mailto:([^"]+)"#', $detailPage, $match)) {
                $eStore->setEmail($match[1]);
            }

            if (preg_match('#(Montag\s+.+?)</div>#', $detailPage, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            } elseif (preg_match('#(Mo\..+?)</div>#', $detailPage, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}
