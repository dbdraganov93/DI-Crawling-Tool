<?php

/*
 * Store Crawler für TrinkFuchs (ID: 71868)
 */

class Crawler_Company_TrinkFuchs_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.trink-fuchs.de/';
        $searchUrl = $baseUrl . 'page/cms/site/index.php/filialen2';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#Filialen</h3>(.+?)</div#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#a[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $aStoreUrls = array();
        $aConceptPartner = array();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<td[^>]*>\s*<img[^>]*konzept#i';
            if (preg_match($pattern, $page)) {
                $pattern = '#a[^>]*href="\/(' . $singleStoreUrl . '\/[^"]+?)"#';
                if (preg_match_all($pattern, $page, $storeStreetUrlMatches)) {
                    foreach ($storeStreetUrlMatches[1] as $singleStoreStreetUrl) {
                        $aConceptPartner[] = $baseUrl . $singleStoreStreetUrl;
                    }
                    continue;
                }
                $aConceptPartner[] = $storeDetailUrl;
                continue;
            }

            $pattern = '#a[^>]*href="\/(' . $singleStoreUrl . '\/[^"]+?)"#';
            if (preg_match_all($pattern, $page, $storeStreetUrlMatches)) {
                foreach ($storeStreetUrlMatches[1] as $singleStoreStreetUrl) {
                    $aStoreUrls[] = $baseUrl . $singleStoreStreetUrl;
                }
                continue;
            }

            $aStoreUrls[] = $storeDetailUrl;
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aConceptPartner as $singleConceptPartner) {
            $sPage->open($singleConceptPartner);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<td[^>]*>\s*<p[^>]*>\s*<strong[^>]*>\s*(.+?)\s*<br[^>]*>\s*([^<]+?)(\s*<[^>]*>\s*)+(\d{5}\s+[^<]+?)\s*</p>#is';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->info($companyId . ': unable to get store infos: ' . $singleConceptPartner);
                continue;
            }
            
            $pattern = '#fon:?<[^>]*>([^<]+?)<#';
            if (preg_match($pattern, $page, $storePhoneMatch))
            {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }
            
            $pattern = '#fax:?<[^>]*>([^<]+?)<#';
            if (preg_match($pattern, $page, $storeFaxMatch))
            {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }

            $eStore->setTitle('TrinkFuchs Konzeptpartner')
                    ->setSubtitle($storeInfoMatch[1])
                    ->setAddress($storeInfoMatch[2], $storeInfoMatch[4]);

            $cStores->addElement($eStore);
        }

        foreach ($aStoreUrls as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}.+?)\s*</p#s';
            if (!preg_match($pattern, $page, $storeInfoMatch)) {
                $this->_logger->info($companyId . ': unable to get store infos: ' . $singleStoreUrl);
                continue;
            }

            $pattern = '#ffnungszeiten(.+?)</td#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }
            
            $pattern = '#fon:?<[^>]*>([^<]+?)<#';
            if (preg_match($pattern, $page, $storePhoneMatch))
            {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }
            
            $pattern = '#fax:?<[^>]*>([^<]+?)<#';
            if (preg_match($pattern, $page, $storeFaxMatch))
            {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }

            $pattern = '#<td[^>]*>\s*<ul[^>]*>\s*(.+?)\s*</ul>#s';
            if (preg_match_all($pattern, $page, $textListMatches)) {
                $strText = '';
                foreach ($textListMatches[1] as $singleTextMatch) {
                    $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
                    if (preg_match_all($pattern, $singleTextMatch, $singleTextMatches)) {
                        if (strlen($strText)) {
                            $strText .= '<br/><br/>';
                        }
                        $strText .= implode('<br/>', $singleTextMatches[1]);
                    }
                }
            }

            $eStore->setAddress($storeInfoMatch[1], $storeInfoMatch[2])
                    ->setText($strText);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
