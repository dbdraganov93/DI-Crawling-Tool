<?php
/**
 * Store Crawler für Magasins U FR (IDs: 72347 - )
 */

class Crawler_Company_MagasinsUFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.magasins-u.com/';
        $searchUrl = $baseUrl . 'annuaire-departement';
        $sPage = new Marktjagd_Service_Input_Page();

        $aCompanyPattern = array(
            '72347' => 'superu',
            '72348' => 'uexpress',
            '72349' => 'hyperu',
            '72350' => 'marcheu'
        );

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/(\d+-[^"]+?)"[^>]*class="link-annuaire-dpt#';
        if (!preg_match_all($pattern, $page, $departmentMatches)) {
            throw new Exception($companyId . ': unable to get any department links.');
        }

        $aStoreUrls = array();
        foreach ($departmentMatches[1] as $singleDepartment) {
            $departmentUrl = $baseUrl . urlencode($singleDepartment);

            $sPage->open($departmentUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="\/(' . $aCompanyPattern[$companyId] . '-[^"]+?)"[^>]*class="link-annuaire-dpt#';
            if (!preg_match_all($pattern, $page, $departmentMatches)) {
                $this->_logger->info($companyId . ': no stores for department: ' . $departmentUrl);
                continue;
            }

            $aStoreUrls = array_merge($aStoreUrls, $departmentMatches[1]);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreUrls as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<dl[^>]*class="horaires"[^>]*>\s*(.+?)\s*<\/dl#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized(preg_replace('#–#', ',', $storeHoursMatch[1]), 'text', TRUE, 'fra');
            }

            $pattern = '#tél\s*<[^>]*>\s*:?\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#data-codemagasin="(\d+)"#';
            if (preg_match($pattern, $page, $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($addressMatch[1], 'FR')
                ->setZipcodeAndCity($addressMatch[2])
                ->setWebsite($storeDetailUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);

    }
}