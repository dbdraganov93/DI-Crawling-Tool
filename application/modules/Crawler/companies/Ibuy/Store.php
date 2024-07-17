<?php

/*
 * Store Crawler für ibuy (ID: 71878)
 */

class Crawler_Company_Ibuy_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ibuy.de/';
        $searchUrl = $baseUrl . 'ibuy-stores/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*title="ibuy[^>]*href="((http://www.ibuy.de/)?ibuy-[^"]+?\/)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store detail urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $singleStoreUrl;
            if (!preg_match('#^http#', $storeDetailUrl)) {
                $storeDetailUrl = $searchUrl . $singleStoreUrl;
            }

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div[^>]*id="text"[^>]*>.+?</h1>\s*(.+?)\s*<a#s';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $storeDetailUrl);
                continue;
            }

            $aInfos = preg_split('#(\s*<[^>]*>\s*)+#', $infoListMatch[1]);

            $strTimes = '';
            foreach ($aInfos as $singleInfo) {
                if (preg_match('#tel#i', $singleInfo)) {
                    $eStore->setPhoneNormalized($singleInfo);
                    continue;
                }

                if (preg_match('#fax#i', $singleInfo)) {
                    $eStore->setFaxNormalized($singleInfo);
                    continue;
                }

                if (preg_match('#uhr#i', $singleInfo)) {
                    if (strlen($strTimes)) {
                        $strTimes .= ', ';
                    }
                    $strTimes .= $singleInfo;
                }
            }
            
            for ($i = 0; $i < count($aInfos); $i++) {
                if (preg_match('#^\d{5}#', $aInfos[$i])) {
                    $eStore->setAddress($aInfos[$i - 1], $aInfos[$i]);
                    break;
                }
            }
            
            $eStore->setStoreHoursNormalized($strTimes);

            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
