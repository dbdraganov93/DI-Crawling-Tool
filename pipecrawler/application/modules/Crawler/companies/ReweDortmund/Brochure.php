<?php

/**
 * Brochure Crawler für Rewe Dortmund (ID: 73661)
 */
class Crawler_Company_ReweDortmund_Brochure extends Crawler_Generic_Company
{
    private $weekNr;
    private $weekYear;

    public function crawl($companyId)
    {
        $sTime = new Marktjagd_Service_Text_Times();
        $this->weekNr = $sTime->getWeekNr('next');
        $this->weekYear = $sTime->getWeeksYear('next');


        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $sFtp->connect($companyId, TRUE);
        $localBrochures = $this->getFilesFromNextCloud($localPath, $this->weekNr . '_' . date('y'));

        $localBrochure = array_pop($localBrochures);
        $storePattern3 = '#stores-Prospekt-3-Stores.xlsx$#';
        $storePattern5 = '#stores-Prospekt-5-Stores\.xlsx$#';
        $storePattern11 = '#stores-Prospekt-11-Stores\.xlsx$#';
        $localStoreFile3 = '';
        $localStoreFile5 = '';
        $localStoreFile11 = '';

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match($storePattern3, $singleFile)) {
                $localStoreFile3 = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
            if (preg_match($storePattern5, $singleFile)) {
                $localStoreFile5 = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
            if (preg_match($storePattern11, $singleFile)) {
                $localStoreFile11 = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        if (empty($localStoreFile3)) {
            throw new Exception('did not find store file: ' . $storePattern3);
        }

        if (empty($localStoreFile5)) {
            throw new Exception('did not find store file: ' . $storePattern5);
        }

        if (empty($localStoreFile11)) {
            throw new Exception('did not find store file: ' . $storePattern11);
        }

        $this->_logger->info('found store file: ' . $localStoreFile3);
        $this->_logger->info('found store file: ' . $localStoreFile5);
        $this->_logger->info('found store file: ' . $localStoreFile11);

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();

        $aData3 = $sPss->readFile($localStoreFile3, true)->getElement(0)->getData();
        $storeNumbers3 = [];
        foreach ($aData3 as $singleStore) {
            if (array_key_exists('Filialnr.', $singleStore) and !empty($singleStore['Filialnr.'])) {
                $storeNumbers3[] = $singleStore['Filialnr.'];
            }
        }

        $aData5 = $sPss->readFile($localStoreFile5, true)->getElement(0)->getData();
        $storeNumbers5 = [];
        foreach ($aData5 as $singleStore) {
            if (array_key_exists('Filialnr.', $singleStore) and !empty($singleStore['Filialnr.'])) {
                $storeNumbers5[] = $singleStore['Filialnr.'];
            }
        }

        $aData11 = $sPss->readFile($localStoreFile11, true)->getElement(0)->getData();
        $storeNumbers11 = [];
        foreach ($aData11 as $singleStore) {
            if (array_key_exists('Filialnr.', $singleStore) and !empty($singleStore['Filialnr.'])) {
                $storeNumbers11[] = $singleStore['Filialnr.'];
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $brochurePattern = '#KW' . $this->weekNr . '-' . $this->weekYear . '\.pdf$#';

        $storeNumbers = array_merge($storeNumbers3, $storeNumbers5, $storeNumbers11);

        $cBrochures->addElement($this->integrateBrochure($companyId, $localBrochure, $storeNumbers, 'allStores'));

        return $this->getResponse($cBrochures, $companyId);
    }

    public function integrateBrochure($companyId, $localBrochure, $storeNumbers, $short): Marktjagd_Entity_Api_Brochure
    {

        if (empty($localBrochure)) {
            throw new Exception('did not find brochure file: ' . $localBrochure);
        }

        $sPdf = new Marktjagd_Service_Output_Pdf();
        $pageCount = $sPdf->getPageCount($localBrochure);

        for ($i = 0; $i < $pageCount; $i++) {
            // Clickout on first age
            $aData[] = [
                'page' => $i,
                'link' => 'https://www.rewe-dortmund.de/angebote/',
                'startX' => '340',
                'endX' => '390',
                'startY' => '740',
                'endY' => '790'
            ];
        }

        $coordFileName = APPLICATION_PATH . '/../public/files/coordinates_' . $companyId . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aData));
        fclose($fh);

        $localBrochure = $sPdf->setAnnotations($localBrochure, $coordFileName);

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('REWE: Wochenangebote')
            ->setBrochureNumber('KW' . $this->weekNr . '-' . $this->weekYear . '-' . $short)
            ->setUrl($localBrochure)
            #->setStoreNumber(implode(',', $storeNumbers))
            ->setVisibleStart(date('d.m.Y', strtotime('this week sunday')))
            ->setStart(date('d.m.Y', strtotime('next week monday')))
            ->setEnd(date('d.m.Y', strtotime('next week saturday')));

        return $eBrochure;
    }

    private function getFilesFromNextCloud(string $localPath, string $remotePath = '', string $extension = 'pdf')
    {


        # set up the config to the MC NextCloud
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', 'production');
        $username = $configIni->nextcloud->mc->username;
        $password = $configIni->nextcloud->mc->password;
        $url =  'https://nextcloud.media-central.com/nextcloud/remote.php/webdav/Offerista%20(2)/REWE/KW' . $remotePath
            . '/HZ_KW' . $remotePath . '_einzel.pdf';

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'UTF-8',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERNAME => $username,
            CURLOPT_PASSWORD => $password,
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_POSTFIELDS => '<?xml version="1.0" encoding="UTF-8"?>
                 <d:propfind xmlns:d="DAV:">-->
                   <d:prop xmlns:oc="http://owncloud.org/ns">-->
                     <d:getlastmodified/>-->
                   </d:prop>-->
                 </d:propfind>',
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        preg_match_all('#<d:href>(?<files>[^<]+?\.' . $extension . ')<\/d:href>#', $response, $matches);

        $resultFiles = [];
        $sHttp = new Marktjagd_Service_Transfer_Http();
        foreach ($matches['files'] as $remoteFile) {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://nextcloud.media-central.com' . $remoteFile,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => 'UTF-8',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_USERNAME => $username,
                CURLOPT_PASSWORD => $password,
            ));
            $response = curl_exec($curl);

            file_put_contents($localPath . basename($remoteFile), $response);

            $resultFiles[basename($remoteFile)] = $localPath . basename($remoteFile);
        }
        return $resultFiles;
    }
}
