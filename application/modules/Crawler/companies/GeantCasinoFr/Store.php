<?php
/**
 * Store Crawler für Géant Casino FR (ID: 72411)
 */

class Crawler_Company_GeantCasinoFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://magasins.geantcasino.fr';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*title="Magasin#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $aDetailUrls = array();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*ButtonFull[^>]*href="([^"]+?\/([^\/"]+?))"#';
            if (!preg_match($pattern, $page, $storeDetailUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get store detail url: ' . $singleStoreUrl);
                continue;
            }

            $aDetailUrls[$storeDetailUrlMatch[2]] = $storeDetailUrlMatch[1];
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aDetailUrls as $storeNumber => $storeUrl) {
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*(.+?)\s*<\/span#';
            if (!preg_match_all($pattern, $page, $storeInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $storeUrl);
                continue;
            }

            $aInfos = array_combine($storeInfoMatches[1], $storeInfoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<a[^>]*itemprop="telephone"[^>]*>([^<]+?)<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#itemprop="openingHours"[^>]*content="([^"]+?)"#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $aCity = preg_split('#\s*-\s*#', $aInfos['addressLocality']);
            foreach ($aCity as &$singlePart) {
                $singlePart = ucwords(strtolower($singlePart));
            }

            $aInfos['addressLocality'] = implode('-', $aCity);

            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'], 'FR')
                ->setZipcode($aInfos['postalCode'])
                ->setCity($aInfos['addressLocality'])
                ->setStoreNumber($storeNumber)
                ->setWebsite($storeUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}