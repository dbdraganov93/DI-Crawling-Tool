<?php

class Crawler_Company_PitStop_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pitstop.de/';
        $searchUrl = $baseUrl . 'Home/Filiale';
        $sPage = new Marktjagd_Service_Input_Page();
        $mjGeo = new Marktjagd_Database_Service_GeoRegion();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPhpSpreadsheet = new Marktjagd_Service_Input_PhpSpreadsheet();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.xlsx?$#', $singleRemoteFile)) {
                $locaCampaignFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                break;
            }
        }

        $aData = $sPhpSpreadsheet->readFile($locaCampaignFile, TRUE)->getElement(0)->getData();

        $aCampaignZipcodes = [];
        foreach ($aData as $singleRow) {
            if (preg_match('#^(\d{5})#', $singleRow['PLZ Ort'], $zipcodeMatch)) {
                $aCampaignZipcodes[] = $zipcodeMatch[1];
            }
        }

        $aZipCodes = $mjGeo->findZipCodesByNetSize(10);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aZipCodes as $sZipCode) {
            $sPage->open($searchUrl, [
                'PostalCode' => $sZipCode,
                'Lat' => 0,
                'Lng' => 0,
                'GarageId' => 0
            ]);

            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<ul[^>]*class="garage-finder-list"[^>]*>\s*(.+?)\s*</ul#';
            if (!preg_match($pattern, $page, $aStoreListMatch)) {
                $this->_logger->err($companyId . ': unable to get store-list.');
                continue;
            }

            $pattern = '#<li[^>]*>\s*(.+?)\s*</li#';
            if (!preg_match_all($pattern, $aStoreListMatch[1], $aStoreMatches)) {
                $this->_logger->err($companyId . ': unable to get stores.');
                continue;
            }

            foreach ($aStoreMatches[1] as $sStoreItem) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#class="garage-street"[^>]*>\s*(.+?)\s*<.+class="garage-city"[^>]*>\s*(.+?)\s*<#';
                if (!preg_match($pattern, $sStoreItem, $aAddressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store-address.');
                    continue;
                }

                $pattern = '#class="garage-name"[^>]*>\s*(.+?)\s*<#';
                if (preg_match($pattern, $sStoreItem, $sTitleMatch)) {
                    $eStore->setTitle($sTitleMatch[1]);
                }

                $pattern = '#Tel\.*\:*\s*(.+?)\s*<#';
                if (preg_match($pattern, $sStoreItem, $sTelMatch)) {
                    $eStore->setPhoneNormalized($sTelMatch[1]);
                }

                $eStore->setStreetAndStreetNumber($aAddressMatch[1])
                    ->setZipcodeAndCity($aAddressMatch[2]);

                if (in_array($eStore->getZipcode(), $aCampaignZipcodes)) {
                    $eStore->setDistribution('Kampagne');
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
