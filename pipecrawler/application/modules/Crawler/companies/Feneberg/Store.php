<?php

/*
 * Store Crawler für Feneberg (ID: 80006)
 */

class Crawler_Company_Feneberg_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.feneberg.de';
        $searchUrl = $baseUrl . '/no_cache/markt-finden/';
        $sPage = new Marktjagd_Service_Input_Page();

        $aCampaignZip = [
            '88045',
            '86153',
            '80804',
            '80339',
            '80807',
        ];

        $aCampaignStreet = [
            'Allmandstraße',
            'Willy-Brandt-Platz',
            'Leopoldstraße',
            'Ganghoferstraße',
            'Trappentreustraße',
            'Humperndinckstraße'
        ];

        $extraRadi = [
            'Am Bühlberg',
            'Ursulasrieder Straße',
            'Kalzhofer Straße',
            'Rudolf-Diesel-Straße',
            'Ahornweg'
        ];


        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#show_link"[^>]*>\s*<a[^>]*href="([^"]+)"#';
        if (!preg_match_all($pattern, $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleStore) {
            $storeUrl = $baseUrl . $singleStore;
            $this->_logger->info('checking URL: ' . $storeUrl);

            $sPage->getPage()->setTimeout(120);
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#class="adressBox"[^>]*>\s*(.+?)\s*</div#si';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get address: ' . $storeUrl);
                continue;
            }

            $aAddress = preg_split('#\s*<[^>]*>\s*#', $addressMatch[1]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $strSection = '';
            $pattern = '#class="additionalFeatures"[^>]*>\s*(.+?)\s*</div#si';
            if (preg_match($pattern, $page, $sectionListMatch)) {
                $pattern = '#<li[^>]*>\s*(.+?)\s*</li#si';
                if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                    $strSection = implode(', ', $sectionMatches[1]);
                }
            }

            $pattern = '#class="OpeningHours"[^>]*>\s*(.+?)\s*<\/div>\s*<\/div#si';
            if (!preg_match($pattern, $page, $storeHoursMatch)) {
                $this->_logger->info($companyId . ': unable to get store hours: ' . $storeUrl);
            }

            $eStore->setStreetAndStreetNumber($aAddress[1])
                ->setZipcodeAndCity($aAddress[2])
                ->setPhoneNormalized($aAddress[3])
                ->setSection($strSection)
                ->setStoreHoursNormalized($storeHoursMatch[1])
                ->setWebsite($storeUrl)
                ->setDefaultRadius(5);

            if (strlen($eStore->getZipcode()) != 5) {
                continue;
            }

            if (array_search($eStore->getZipcode(), $aCampaignZip) && array_search($eStore->getStreet(), $aCampaignStreet)) {
                $eStore->setDistribution('Kampagne');
            }

            foreach ($extraRadi as $singleRadius) {
                if (preg_match('#' . preg_replace('#[^\d\wäöü]#i', '', $singleRadius) . '#', preg_replace('#[^\d\wäöü]#i', '', $eStore->getStreet()))) {
                    $eStore->setDefaultRadius(15);
                }
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }

}
