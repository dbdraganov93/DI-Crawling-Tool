<?php

/*
 * Store Crawler für ad Autodienst (ID: 28664)
 */

class Crawler_Company_AdAutodienst_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ad-autodienst.de/';
        $searchUrl = $baseUrl . 'werkstattsuche';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<span[^>]*werkstatt-firmenname[^>]*>\s*<span[^>]*>\s*<a[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Kontakt(.+?)</aside#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list: ' . $storeDetailUrl);
                continue;
            }

            $pattern = '#<span[^>]*glyphicon-home[^>]*>\s*<[^>]*>\s*([^<]+?)\s*(\s*<[^>]*>\s*)*\s*D?-?(\d{5}\s+[^<]+?)\s*<#s';
            if (!preg_match($pattern, $infoListMatch[1], $addressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<span[^>]*glyphicon-envelope[^>]*>\s*(\s*<[^>]*>\s*)*\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $mailMatch)) {
                $eStore->setEmail($mailMatch[2]);
            }

            $pattern = '#<span[^>]*glyphicon-globe[^>]*>\s*(\s*<[^>]*>\s*)*\s*<a[^>]*href="([^"]+?)"#';
            if (preg_match($pattern, $infoListMatch[1], $websiteMatch)) {
                $eStore->setWebsite($websiteMatch[2]);
            }

            $pattern = '#<span[^>]*glyphicon-earphone[^>]*>\s*(\s*<[^>]*>\s*)*\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[2]);
            }

            $pattern = '#<span[^>]*glyphicon-print[^>]*>\s*(\s*<[^>]*>\s*)*\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $infoListMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[2]);
            }

            $pattern = '#<span[^>]*glyphicon-time[^>]*>(.+)#';
            if (preg_match($pattern, $infoListMatch[1], $storeHoursMatch)) {
                if (preg_match('#(Mittagspause:?[^,]*?\s*([^-]+?)\s*-\s*([^,]+?)\s*),#', $storeHoursMatch[1], $lunchBreakMatch)) {
                    $storeHoursMatch[1] = preg_replace('#' . $lunchBreakMatch[1] . '#', '', $storeHoursMatch[1]);
                }
                $sTimes = new Marktjagd_Service_Text_Times();
                $strTimes = $sTimes->generateMjOpenings($storeHoursMatch[1]);
                $aTimes = preg_split('#\s*,\s*#', $strTimes);
                foreach ($aTimes as &$singleDay) {
                    $pattern = '#\s*([A-Z][a-z])\s+([^-]+?)-(.+)#';
                    if (preg_match($pattern, $singleDay, $timePartsMatch)) {
                        $singleDay = $timePartsMatch[1] . ' ' . $timePartsMatch[2] . '-' . $lunchBreakMatch[2];
                        $singleDay .= ',' . $timePartsMatch[1] . ' ' . $lunchBreakMatch[3] . '-' . $timePartsMatch[3];
                    }
                }
                $strTimes = implode(',', $aTimes);
                
                $eStore->setStoreHoursNormalized($strTimes);
            }

            $pattern = '#ul[^>]*class="leistungList"[^>]*>(.+?)</ul#s';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<a[^>]*title="([^"]+?)"#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[3]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
