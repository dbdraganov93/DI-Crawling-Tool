<?php

/**
 * Storecrawler für Manor (CH) (ID: 72138) und Manor Food (ID: 72250)
 */
class Crawler_Company_ManorCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aFiles = $sFtp->listFiles('.', '#stores_list.csv#');
        $localCsvPath = $sFtp->downloadFtpToDir($aFiles[0], $localPath);

        if (is_null($localCsvPath)) {
            throw new Exception($companyId . ': unable to get store list file.');
        }

        $fh = fopen($localCsvPath, 'r');
        while (($line = fgetcsv($fh, null, ";")) !== FALSE) {
            $url = $line[0];
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<div[^>]*class="m\-store\-details\_\_storeinfo"[^>]*>\s*'
                . '<span[^>]*>.*?<\/span>\s*'
                . '<span[^>]*>(.+?)<\/span>\s*'
                . '<span[^>]*>(.+?)<\/span>\s*'
                . '<span[^>]*>(.+?)<\/span>\s*'
                . '#is';

            if (!preg_match($pattern, $page, $matchAddress)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $url);
                continue;
            }
            $storeNumber = substr($url, -3, 3);

            $eStore->setStoreNumber($storeNumber)
                ->setStreetAndStreetNumber($matchAddress[1], 'CH')
                ->setZipcodeAndCity($matchAddress[2])
                ->setPhoneNormalized($matchAddress[3])
                ->setWebsite($url);

            $pattern = '#<ul[^>]*worktime__days[^>]*>(.+?)<\/ul#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $cStores->addElement($eStore);
        }

        fclose($fh);


        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}

