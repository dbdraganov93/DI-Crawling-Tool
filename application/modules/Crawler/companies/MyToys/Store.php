<?php

/*
 * Store Crawler für myToys (ID: 22244)
 */

class Crawler_Company_MyToys_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.mytoys.de';
        $searchUrl = '/c/filialen.html';

        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $ch = curl_init($baseUrl . $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $page = preg_replace('#\s+#', ' ', curl_exec($ch));
        curl_close($ch);

        $pattern = '#<a\s*href="(/c/filiale\-[^"]+)"[^>]*>([^<]+)</a>#';
        if (!preg_match_all($pattern, $page, $aLinkMatches)) {
            throw new Exception('Company ID ' . $companyId . ': could not get store urls from ' . $baseUrl . $searchUrl);
        }

        foreach ($aLinkMatches[1] as $sSingleStoreLink) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $this->_logger->info('open: ' . $baseUrl . $sSingleStoreLink);

            $ch = curl_init($baseUrl . $sSingleStoreLink);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            $page = preg_replace('#\s+#', ' ', curl_exec($ch));
            curl_close($ch);

            if (!preg_match('#<h[^>]*>\s*Anschrift\s*</h\d>\s*<p>(.+?)</p>#', $page, $aAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $baseUrl . $sSingleStoreLink);
                continue;
            }

            $aAddress = preg_split('#\s*<br\s*/>\s*#', $aAddressMatch[1]);
            $countElements = count($aAddress);

            if (preg_match('#<a\s*href="tel:([^"]*)"\s*>#is', $page, $aPhoneMatch)) {
                $eStore->setPhoneNormalized($aPhoneMatch[1]);
            }

            if (preg_match('#<img\s*src="(https://images.mytoys.com/.*?/mt_store_[^"]*)"#is', $page, $aImgMatch)) {
                $eStore->setImage($aImgMatch[1]);
            }

            if (preg_match('#<h2>\s*.*?ffnungszeiten\s*</h2>\s*<p>(.+?)</p>#is', $page, $aHoursMatch)) {
                $eStore->setStoreHoursNormalized($aHoursMatch[1]);
            }

            if (preg_match('#<h2>\s*Sonder.*?ffnungszeiten\s*</h2>\s*<p>(.+?)</p>#is', $page, $aHoursMatch)) {
                $eStore->setStoreHoursNotes($aHoursMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($aAddress[$countElements - 2])
                    ->setZipcodeAndCity($aAddress[$countElements - 1])
                    ->setSubtitle($aAddress[0])
                    ->setWebsite($baseUrl . $sSingleStoreLink);

            $cStores->addElement($eStore);
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

}
