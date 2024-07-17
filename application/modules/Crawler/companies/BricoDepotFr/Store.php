<?php
/**
 * Store Crawler für Brico Depot FR (ID: 72325)
 */

class Crawler_Company_BricoDepotFr_Store extends Crawler_Generic_Company {

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.bricodepot.fr/';
        $searchUrl = $baseUrl . 'la-roche-sur-yon/depot/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->getPage()->setIgnoreRobots(TRUE);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/la-roche-sur-yon\/depot\/([^"]+?)"[^>]*data-text="Voir\s*la\s*fiche"[^>]*#';

        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': no store urls found.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;
            $aStoreInfo = preg_split('#\s*\/\s*#', $singleStoreUrl);

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^-<]*\s*-\s*)?([^<-]+?)\s*(-[^<]*)?\s*<[^>]*>\s*(\d{5}\s+[^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#href=\'tel:([^\']+?)\'#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#<table[^>]*class="[^"]*openingDates"[^>]*>(.+?)<\/table#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1], 'text', TRUE, 'fra');
            }

            $pattern = '#<h6[^>]*class="bd-Insurance-listItemTitle"[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match_all($pattern, $page, $serviceMatches)) {
                $eStore->setService(implode(', ', $serviceMatches[1]));
            }

            $eStore->setStreetAndStreetNumber($addressMatch[2])
                    ->setZipcodeAndCity($addressMatch[4])
                    ->setStoreNumber($aStoreInfo[1]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $filename = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($filename);
    }
}
