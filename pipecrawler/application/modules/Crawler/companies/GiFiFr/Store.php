<?php
/**
 * Store Crawler für GiFi FR (ID: 72379)
 */

class Crawler_Company_GiFiFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://magasins.gifi.fr/';
        $searchUrl = $baseUrl . 'fr';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h2[^>]*class="TitleItemMagasin"[^>]*>\s*<a[^>]*href="([^"]+?)"#i';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#\/([^\/]+)$#';
            if (!preg_match($pattern, $singleStoreUrl, $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get any store number from url: ' . $singleStoreUrl);
            }

            $pattern = '#itemprop="([^"]+?)"[^>]*>(\s*<[^>]*>\s*)?([^<]{3,}?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $singleStoreUrl);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[3]);

            $pattern = '#itemprop="([^"]+?)"[^>]*content="([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $additionalInfoMatches)) {
                $this->_logger->info($companyId . ': unable to get additional store infos: ' . $singleStoreUrl);
            }

            $aAdditionalInfos = array_combine($additionalInfoMatches[1], $additionalInfoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setWebsite($singleStoreUrl)
                ->setStoreNumber($storeNumberMatch[1])
                ->setZipcode($aInfos['postalCode'])
                ->setCity($aInfos['addressLocality'])
                ->setPhoneNormalized($aInfos['telephone'])
                ->setStreetAndStreetNumber($aInfos['streetAddress'], 'fr')
                ->setLatitude($aAdditionalInfos['latitude'])
                ->setLongitude($aAdditionalInfos['longitude'])
                ->setStoreHoursNormalized($aAdditionalInfos['openingHours']);

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}