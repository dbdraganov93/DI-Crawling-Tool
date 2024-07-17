<?php

/**
 *
 * Prospektcrawler für Media Markt (CH) (ID: 72176)
 *
 * Class Crawler_Company_MediaMarktCh_Brochure
 *
 */
class Crawler_Company_MediaMarktCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $cStores = $sApi->findStoresByCompany($companyId);
        $cStores = $cStores->getElements();

        $sFtp->connect($companyId);
        $aPdfs = $sFtp->listFiles('.', '#\.pdf#i');
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aReplacement = array(
            'DE' => '$1?rbtc=pft%7cfly%7cprospekt~de%7c%7cp%7cdisplay-performance_profital_na%7c',
            'FR' => '$1?rbtc=pft%7cfly%7cprospekt~fr%7c%7cp%7cdisplay-performance_profital_na%7c',
            'IT' => '$1?rbtc=pft%7cfly%7cprospekt~it%7c%7cp%7cdisplay-performance_profital_na%7c',
        );

        $modificationData = array(
            'searchPattern' => '(.+)',
        );

        foreach ($aPdfs as $oPdf) {
            $localFile = $sFtp->downloadFtpToDir($oPdf, $localPath);

            $fileName = preg_replace('#\.pdf#', '', $oPdf);
            $aFileName = explode(';', $fileName);

            if (count($aFileName) != 6) {
                throw new Exception($companyId . ': unknown file format');
            }

            $strStoreNumbers = '';
            foreach ($cStores as $eStore) {
                /* @var $eStore Marktjagd_Entity_Api_Store */
                if (preg_match('#^' . $aFileName[5] . '$#i', $sTranslation->findLanguageCodeForZipcode($eStore->getZipcode()))) {
                    if (strlen($strStoreNumbers)) {
                        $strStoreNumbers .= ',';
                    }
                    $strStoreNumbers .= $eStore->getStoreNumber();
                }
            }

            $modificationData['replacePattern'] = $aReplacement[strtoupper($aFileName[5])];
            $storeJsonFile = $localPath . 'exchangeData_' . time() . '.json';

            $fh = fopen($storeJsonFile, 'w+');
            fwrite($fh, json_encode(array($modificationData)));
            fclose($fh);

            $fileReplace = str_replace(array(';', ' '), array('_', ''), $localFile);

            exec('mv \'' . $localFile . '\' \'' . $fileReplace . '\'');

            $localFile = $sPdf->exchange($fileReplace);
            $localFile = $sPdf->modifyLinks($localFile, $storeJsonFile, TRUE);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($aFileName[0])
                ->setUrl($sFtp->generatePublicFtpUrl($localFile))
                ->setStoreNumber($strStoreNumbers)
                ->setStart(date('d.m.Y', strtotime($aFileName[1])))
                ->setEnd(date('d.m.Y', strtotime($aFileName[2])))
                ->setVisibleStart(date('d.m.Y', strtotime($aFileName[3])))
                ->setVisibleEnd(date('d.m.Y', strtotime($aFileName[4])))
                ->setLanguageCode(strtolower($aFileName[5]))
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
