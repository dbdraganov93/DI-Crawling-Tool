<?php

class Crawler_Company_Motoo_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();
        $sGenerator = new Marktjagd_Service_Generator_Url();
        $baseUrl = 'http://www.motoo.de/';
        $searchUrl = $baseUrl . 'index.php?id=133&tx_locator_pi1[zipcode]='
                . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&tx_locator_pi1[city]=&tx_locator_pi1[storename]='
                . '&submit=Finden&tx_locator_pi1[teilehandel]=on&tx_locator_pi1[categories]='
                . '&tx_locator_pi1[country]=DE&tx_locator_pi1[mode]=search'
                . '&tx_locator_pi1[radius]=500';

        $aLinks = $sGenerator->generateUrl($searchUrl, 'zip', '20');

        $cStores = new Marktjagd_Collection_Api_Store();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        foreach ($aLinks as $singleLink) {
            if (!$sPage->open($singleLink)) {
                $logger->log($companyId . ': unable to open store list page for url '
                        . $singleLink, Zend_Log::INFO);
                continue;
            }

            $page = utf8_decode($sPage->getPage()->getResponseBody());

            $pattern = '#<div[^>]*class="partner-info-box class="[^>]*>(.+?)<div[^>]*class="clear"[^>]*>#';
            if (!preg_match_all($pattern, $page, $aStoreMatches)) {
                continue;
            }

            foreach ($aStoreMatches[1] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<h4[^>]*>\s*(.+?)\s*<#';
                if (preg_match($pattern, $singleStore, $aTitleMatch)) {
                    $eStore->setTitle($aTitleMatch[1]);
                }

                $pattern = '#<div[^>]*class="left"[^>]*>\s*<p[^>]*>\s*(.+?)\s*</p>#';
                if (!preg_match($pattern, $singleStore, $aAddressMatch)) {
                    $logger->log($companyId . ': unable to get store address.', Zend_Log::ERR);
                    continue;
                }

                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $aAddressMatch[1]);

                $eStore->setStreet($sAddress->extractAddressPart('street', strip_tags($aAddress[0])))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', strip_tags($aAddress[0])))
                        ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                        ->setZipcode($sAddress->extractAddressPart('zip', $aAddress[1]));

                $pattern = '#Tel(.+?)<#';
                if (preg_match($pattern, $singleStore, $aTelMatch)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($aTelMatch[1]));
                }

                $pattern = '#Fax(.+?)<#';
                if (preg_match($pattern, $singleStore, $aFaxMatch)) {
                    $eStore->setFax($sAddress->normalizePhoneNumber($aFaxMatch[1]));
                }

                $pattern = '#mailto\:([^"]*)"#';
                if (preg_match($pattern, $singleStore, $aMailMatch)) {
                    $eStore->setEmail(strip_tags($aMailMatch[1]));
                }

                $pattern = '#<a[^>]*href="(http\:\/\/www.+?)"#';
                if (preg_match($pattern, $singleStore, $aWebsiteMatch)) {
                    $eStore->setWebsite($aWebsiteMatch[1]);
                }

                $pattern = '#Öffnungszeiten(.+?)</span#';
                if (preg_match($pattern, $singleStore, $aStoreHoursMatch)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings(strip_tags($aStoreHoursMatch[1])));
                }
                
                $eStore->setStoreNumber(substr(
                    md5(
                            $eStore->getZipcode()
                            . $eStore->getCity()
                            . $eStore->getStreet()
                            . $eStore->getStreetNumber()
                            )
                    , 0, 25));

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
