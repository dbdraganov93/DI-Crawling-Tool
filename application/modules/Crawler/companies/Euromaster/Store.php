<?php

/*
 * Store Crawler für Euromaster (ID: 28744)
 */

class Crawler_Company_Euromaster_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://filiale.euromaster.de/';
        $searchUrl = $baseUrl . 'search?query=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&st_any%5BCATEGORIE_DE_VEHICULE%5D%5B%5D=';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        foreach ($sFtp->listFiles('.', '#campaign\.xls#') as $singleFile) {
            $localCampaignPath = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }

        $sFtp->close();

        $aStores = $sExcel->readFile($localCampaignPath, TRUE)->getElement(0)->getData();

        $aStoreNumbers = array();
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStores as $singleStore) {
            $aStoreNumbers[] = $singleStore['identifier'];
            if (!preg_match('#ACTIVE#', $singleStore['status']) || !preg_match('#DE#', $singleStore['country'])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleStore['identifier'])
                    ->setStreetAndStreetNumber($singleStore['streetAndNumber'])
                    ->setZipcode($singleStore['zip'])
                    ->setCity($singleStore['city'])
                    ->setLatitude($singleStore['lat'])
                    ->setLongitude($singleStore['lng'])
                    ->setPhoneNormalized($singleStore['phone'])
                    ->setFaxNormalized($singleStore['fax'])
                    ->setWebsite($singleStore['website'])
                    ->setEmail($singleStore['email'])
                    ->setStoreHoursNormalized(preg_replace('#\=#', ' ', $singleStore['openingHours']))
                    ->setDistribution('Kampagne');

            $cStores->addElement($eStore);
        }

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 50);

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="lf_results_group_by_city"[^>]*>(.+?)</ul#';
            if (!preg_match_all($pattern, $page, $storeMatches)) {
                $this->_logger->info($companyId . ': no store for ' . $singleUrl);
                continue;
            }

            foreach ($storeMatches[1] as $singleStore) {
                $pattern = '#id="widget(\d+?)"#';
                if (!preg_match($pattern, $singleStore, $storeNumberMatch)) {
                    $this->_logger->info($companyId . ': unable to get store number:' . $singleStore);
                } elseif (in_array($storeNumberMatch[1], $aStoreNumbers)) {
                    $this->_logger->info($companyId . ': store ' . $storeNumberMatch[1] . ' already in collection. skipping...');
                    continue;
                }

                $pattern = '#<address[^>]*>\s*([^<]+?)\s*<(.+?)>\s*D?-?\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*</span#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#<h4[^>]*>\s*<a[^>]*href="\/([^"]+?)"#';
                if (preg_match($pattern, $singleStore, $websiteMatch)) {
                    $eStore->setWebsite($baseUrl . $websiteMatch[1]);
                    
                    $sPage->open($eStore->getWebsite());
                    $page = $sPage->getPage()->getResponseBody();
                    
                    $pattern = '#<div[^>]*id="openinghours"[^>]*>(.+?)</div#';
                    if (preg_match($pattern, $page, $storeHoursMatch)) {
                        $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                    }
                    
                    $pattern = '#<div[^>]*class="service"[^>]*>\s*<h5[^>]*>\s*<a[^>]*privatkunde[^>]*>\s*([^<]+?)\s*<#';
                    if (preg_match_all($pattern, $page, $serviceMatches)) {
                        $eStore->setService(implode(', ', $serviceMatches[1]));
                    }
                    
                    $pattern = '#Tel:?\s*<[^>]*>([^<]+?)<#';
                    if (preg_match($pattern, $page, $phoneMatch)) {
                        $eStore->setPhoneNormalized($phoneMatch[1]);
                    }
                    
                    $pattern = '#Fax:?\s*<[^>]*>([^<]+?)<#';
                    if (preg_match($pattern, $page, $faxMatch)) {
                        $eStore->setFaxNormalized($faxMatch[1]);
                    }
                    
                    $pattern = '#Kontakt:?\s*<[^>]*>([^<]+?)<#';
                    if (preg_match($pattern, $page, $mailMatch)) {
                        $eStore->setEmail($mailMatch[1]);
                    }
                }

                $eStore->setAddress($addressMatch[1], $addressMatch[3])
                        ->setStoreNumber($storeNumberMatch[1]);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
