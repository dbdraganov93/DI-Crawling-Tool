<?php
/**
 * Store Crawler für Cora FR (ID: 72323)
 */

class Crawler_Company_CoraFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.cora.fr';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a[^>]*href="([^"]+?)"[^>]*data-idmag="(\d+)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < count($storeUrlMatches[0]); $i++) {
            $storeUrl = $storeUrlMatches[1][$i];
            $storeId = $storeUrlMatches[2][$i];

            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="bloc\s*adresse"[^>]*>\s*(.+?)\s*<\/p>\s*<\/div#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeUrl);
                continue;
            }

            $pattern = '#<div[^>]*class="bloc\s*horaires"[^>]*>\s*(.+?)\s*<\/strong>\s*<\/p>#';
            if (!preg_match($pattern, $page, $storeHoursMatch)) {
                $this->_logger->err($companyId . ': unable to get store hours: ' . $storeUrl);
                continue;
            }

            $pattern = '#<div[^>]*class="bloc\s*contact"[^>]*>\s*(.+?)\s*<\/p>\s*<\/div#';
            if (!preg_match($pattern, $page, $contactMatch)) {
                $this->_logger->err($companyId . ': unable to get store contact infos: ' . $storeUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<strong[^>]*>([^\.]+?)[\.|,]#';
            if (preg_match($pattern, $storeHoursMatch[1], $storeHoursFilteredMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursFilteredMatch[1], 'text', TRUE, 'fra');
            }

            $pattern = '#<strong[^>]*>\s*([^<]+?)\s*(\s*<[^>]*>\s*)*(\d{5}\s+[^<]+?)\s*<#';
            if (preg_match($pattern, $addressMatch[1], $addressDetailMatch)) {
                $eStore->setAddress($addressDetailMatch[1], $addressDetailMatch[3]);
            }

            $pattern = '#lat\.?\s*([^\s]+?)\s*\/\s*lon\.?\s*(.+)#i';
            if (preg_match($pattern, $addressMatch[1], $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                    ->setLongitude($geoMatch[2]);
            }

            $pattern = '#href="tel[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $contactMatch[1], $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#fax\s*\.?\s*:?\s*<[^>]*>\s*(.+)#is';
            if (preg_match($pattern, $contactMatch[1], $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $eStore->setStoreNumber($storeId)
                ->setWebsite($storeUrl);

            if (!strlen($eStore->getStoreHours())) {
                $eStore->setStoreHoursNormalized(preg_replace('#(1e.+)#', '', $storeHoursMatch[1]));
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
