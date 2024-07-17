<?php
/**
 * Store Crawler für Fnac FR (ID: 72410)
 */

class Crawler_Company_FnacFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.fnac.com/';
        $searchUrl = $baseUrl . 'localiser-magasin-fnac/w-4';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<span[^>]*class="StoreFinder-shopName">\s*<a[^>]*href="(http[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#var\s*FnacStore\s*=\s*([^;]+?);#';
            if (!preg_match($pattern, $page, $infoMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $jInfos = json_decode(preg_replace('#\},\s*\]#', '}]', $infoMatch[1]));

            $eStore = new Marktjagd_Entity_Api_Store();

            $aCoord = preg_split('#\s*,\s*#', $jInfos->Store[0]->coord);
            $eStore->setLatitude(preg_replace('#\(#', '', $aCoord[0]))
                ->setLongitude(preg_replace('#\)#', '', $aCoord[1]));

            $eStore->setStoreNumber($jInfos->Store[0]->EAGId)
                ->setCity($jInfos->Store[0]->CityName)
                ->setStreetAndStreetNumber($jInfos->Store[0]->AddressLine, 'FR')
                ->setZipcode($jInfos->Store[0]->ZipCode)
                ->setPhoneNormalized(preg_replace('#\s+\([^\)]+\)\s*$#', '', $jInfos->Store[0]->Phone))
                ->setStoreHoursNormalized(preg_replace(array('#&#', '#\/#'), array(',', '-'), $jInfos->Store[0]->Opening), 'text', TRUE, 'FR')
                ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}