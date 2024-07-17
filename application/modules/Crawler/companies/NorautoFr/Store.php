<?php
/**
 * Store Crawler für Norauto FR (ID: )
 */

class Crawler_Company_NorautoFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.norauto.fr/';
        $searchUrl = $baseUrl . 'INTERSHOP/web/WFS/NI-NOFR-Site/fr_FR/-/EUR/IncludeAjaxMountingCenter-FromSearchPopup?'
            . 'GroupID=Header&SelectCenterClass=js_SelectCenterSession&Value=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 50);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="list-center"[^>]*>\s*<ul[^>]*>(.+?)<\/ul>#';
            if (!preg_match($pattern, $page, $storeListMatch)) {
                $this->_logger->info($companyId . ': no stores for ' . $singleUrl);
                continue;
            }

            $pattern = '#<li[^>]*>\s*<div[^>]*>(.+?)<\/li#';
            if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores from list.');
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5})\s*-\s*([^<]+?)\s*<#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#tél\s*:?\s*([^<]+?)\s*<#i';
                if (preg_match($pattern, $singleStore, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#fax\s*:?\s*([^<]+?)\s*<#i';
                if (preg_match($pattern, $singleStore, $faxMatch)) {
                    $eStore->setFaxNormalized($faxMatch[1]);
                }

                $pattern = '#horaires\s*du\s*magasin(.+?)<\/table#i';
                if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized(preg_replace('#-#', ',', $storeHoursMatch[1]), 'text', TRUE, 'fra');
                }

                $eStore->setStreetAndStreetNumber($addressMatch[1], 'FR')
                    ->setZipcode($addressMatch[2])
                    ->setCity(ucwords(strtolower($addressMatch[3])));

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}