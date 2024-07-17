<?php
/**
 * Brochure Crawler für Intermarché FR (ID: 72320)
 */

class Crawler_Company_IntermarcheFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://prod.inter-ecatalgoapi.monkees.pro/';
        $searchUrl = $baseUrl . 'v1/catalogs/';
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $configCrawler = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $aFilesDownloaded = array();
        foreach ($cStores as $eStore) {
            $ch = curl_init($searchUrl . $eStore->getStoreNumber());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'customer: intermarche'));
            $result = curl_exec($ch);

            $jInfo = json_decode($result);

            if ($jInfo->count == 0) {
                continue;
            }

            foreach ($jInfo->elements as $singleBrochure) {
                if (strtotime('now') > strtotime($singleBrochure->validity_end_date)) {
                    continue;
                }

                $filePath = $localPath . $singleBrochure->slug_catalog . $singleBrochure->version . '.pdf';
                if (!in_array($singleBrochure->slug_catalog . $singleBrochure->version, $aFilesDownloaded)) {
                    $brochureDownloadUrl = $baseUrl . 'v1/catalog/' . $singleBrochure->slug_catalog . '/' . $singleBrochure->version . '/download?pages=all';
                    $ch = curl_init($brochureDownloadUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                    curl_setopt($ch, CURLOPT_HEADER, FALSE);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'customer: intermarche'));
                    $result = curl_exec($ch);
                    curl_close($ch);

                    $fh = fopen($filePath, 'w+');
                    fputs($fh, $result);
                    fclose($fh);

                    $aFilesDownloaded[] = $singleBrochure->slug_catalog . $singleBrochure->version;
                }

                $fileFound = FALSE;
                foreach (scandir($localPath) as $singleFile) {
                    if (preg_match('#' . basename($filePath) . '#', $singleFile)) {
                        $fileFound = TRUE;
                    }
                }

                if ($fileFound) {
                    $strFile = $sHttp->generatePublicHttpUrl($filePath);
                } else {
                    $this->_logger->info($companyId . ': file already stored in bucket: ' . $singleBrochure->slug_catalog . $singleBrochure->version . '.pdf');
                    $strFile = 'https://s3.' . $configCrawler->crawler->s3->region . '.amazonaws.com/' . $configCrawler->crawler->s3->bucketname . '/http/' . $singleBrochure->slug_catalog . $singleBrochure->version . '.pdf';
                }

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setBrochureNumber($singleBrochure->slug_catalog . $singleBrochure->version)
                    ->setUrl($strFile)
                    ->setTitle($singleBrochure->name)
                    ->setStart(date('d.m.Y', strtotime($singleBrochure->validity_start_date)))
                    ->setEnd(date('d.m.Y', strtotime($singleBrochure->validity_end_date)))
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet')
                    ->setStoreNumber($eStore->getStoreNumber());

                $cBrochures->addElement($eBrochure);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
