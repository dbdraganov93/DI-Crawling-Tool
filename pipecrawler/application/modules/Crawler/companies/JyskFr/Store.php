<?php

/*
 * Store Crawler für JYSK FR (ID: 72362)
 */

class Crawler_Company_JyskFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://com.jysk.fr/';
        $searchUrl = $baseUrl . 'lb/nos-magasins/chercher-des-magasins.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);


        $pageCount = 0;
        $cStores = new Marktjagd_Collection_Api_Store();
        while ($pageCount >= 0) {
            $sPage->open($searchUrl, array('tx_dblfilialen_pi1[plz]' => '85000', 'tx_dblfilialen_pi1[ort]' => 'La%20Roche-sur-Yon', 'tx_dblfilialen_pi1[page]' => $pageCount++));
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="pos-address"[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div>#s';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': no stores available on page: ' . $pageCount);
                break;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#<p[^>]*>\s*([^<]+?)\s*<[^>]*>(\s*[^<]+?\s*<[^>]*>)?\s*(\d{5}\s+[A-Z][^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#phone:?([^<]+?)<#';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#fax:?([^<]+?)<#';
                if (preg_match($pattern, $singleStore, $faxMatch)) {
                    $eStore->setFaxNormalized($faxMatch[1]);
                }

                $pattern = '#Horaires\s*d\'ouvertures(.+?)</div#';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fr');
                }

                $eStore->setAddress($addressMatch[1], $addressMatch[3], 'fr')
                    ->setSubtitle(trim(strip_tags($addressMatch[2])));

                if (strlen($addressMatch[2])) {
                    $eStore->setStreetAndStreetNumber($addressMatch[2], 'fr')
                    ->setSubtitle($addressMatch[1]);
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
