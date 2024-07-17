<?php

/*
 * Store Crawler für Küchen Quelle (ID: 29227)
 */

class Crawler_Company_KuechenQuelle_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.kuechen-quelle.de/';
        $searchUrl = $baseUrl . 'Kuechenstudio/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aImages = array();
        foreach ($sFtp->listFiles() as $singleFile) {
            if (!preg_match('#.*[^A-ZÄÖÜ]([A-ZÄÖÜ][a-zäöüß]+).*\.\w{3}$#', $singleFile, $cityMatch)) {
                continue;
            }
            $aImages[$cityMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/Kuechenstudio\/([^"]+?)"[^>]*class="pin#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $searchUrl . $singleStoreUrl;
            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#itemprop="(street-address|streetAddress)"[^>]*>(.+?)</p>#';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $aAddress = preg_split('#\s*\,\s*#', strip_tags($storeAddressMatch[2]));

            $pattern = '#ffnungszeiten(.+?)</p#si';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }

            $pattern = '#itemprop="tel[^>]*>([^<]+?)<#';
            if (preg_match($pattern, $page, $storePhoneMatch)) {
                $eStore->setPhoneNormalized($storePhoneMatch[1]);
            }

            $pattern = '#fax\:?\s*<[^>]*>([^<]+?)<#i';
            if (preg_match($pattern, $page, $storeFaxMatch)) {
                $eStore->setFaxNormalized($storeFaxMatch[1]);
            }

            $pattern = '#mailto:([^"]+?)"#i';
            if (preg_match($pattern, $page, $storeMailMatch)) {
                $eStore->setEmail($storeMailMatch[1]);
            }

            $pattern = '#class="exh4"[^>]*>(.+?)<#';
            if (preg_match_all($pattern, $page, $serviceMatches)) {
                $eStore->setService(implode(', ', $serviceMatches[1]));
            }

            $eStore->setStreetAndStreetNumber($aAddress[0])
                    ->setZipcodeAndCity($aAddress[1])
                    ->setWebsite($storeDetailUrl);
            
            if (array_key_exists(preg_replace('#([^-]+?)-(.+)#', '$1', $eStore->getCity()), $aImages)) {
                $eStore->setImage($sFtp->generatePublicFtpUrl($aImages[preg_replace('#([^-]+?)-(.+)#', '$1', $eStore->getCity())]));
            }

            if (preg_match('#krefeld#i', $eStore->getCity())) {
                continue;
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
