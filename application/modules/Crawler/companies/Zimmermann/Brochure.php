<?php

/*
 * Prospekt Crawler für Zimmermann Sonderposten (ID: 67603)
 */

class Crawler_Company_Zimmermann_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        ini_set('memory_limit', '1G');
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);

        $baseUrl = 'http://layout.zimmermann.de/';
        $searchUrl = $baseUrl . 'Zimmermann_KW' . date('W', strtotime('next week')) . '-' . $sTimes->getWeeksYear('next') . '.zip';

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sHttp->getRemoteFile($searchUrl, $localPath);

        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#\.zip$#', $singleFile)) {
                $sArchive->unzip($localPath . $singleFile, $localPath . 'unzipped/');
            }
        }

        $aFiles = array();
        foreach (scandir($localPath . 'unzipped/') as $singleUnzippedFolder) {
            if (preg_match('#^\.#', $singleUnzippedFolder)) {
                continue;
            }
            $pattern = '#(\d{1,2})\s*-?\s*seiter#i';
            if (preg_match($pattern, $singleUnzippedFolder, $siteAmountMatch)) {
                $pathToCheck = $localPath . 'unzipped/' . $singleUnzippedFolder;
                $aFilesToGet = scandir($pathToCheck);
                $iFiles = str_pad((string) count($aFilesToGet) - 2, 2, '0', STR_PAD_LEFT);
                foreach ($aFilesToGet as $singleFile) {
                    if (preg_match('#seite_(01_kw|\d{2}_[^\_]+?_kw|' . $iFiles . '_kw)[^\.]+?\.pdf#', $singleFile, $siteMatch)) {
                        $aFiles[(string) $siteAmountMatch[1]][(string) $siteMatch[1]] = $pathToCheck . '/' . $singleFile;
                    }
                }
            } else {
                $aFilesToGet = scandir($localPath . 'unzipped/');
                $iFiles = str_pad((string) count($aFilesToGet) - 2, 2, '0', STR_PAD_LEFT);
                if (preg_match('#seite_(\d{2})[^\.]+?\.pdf#', $singleUnzippedFolder, $siteMatch)
                        && !preg_match('#[A-ZÄÖÜ]{3}#', $singleUnzippedFolder)) {
                    $aFiles['no_subfolder'][(string) $siteMatch[1]] = $localPath . 'unzipped/' . $singleUnzippedFolder;
                }
            }
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aFiles as $siteAmount => $singleBrochureFolder) {
            $localJoinedPath = $sPdf->merge($singleBrochureFolder, $localPath);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Sonderangebote & Sonderposten')
                    ->setUrl($sCsv->generatePublicBrochurePath($localJoinedPath))
                    ->setVariety('leaflet')
                    ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), $sTimes->getWeekNr('next'), 'Mo'))
                    ->setEnd($sTimes->findDateForWeekday($sTimes->getWeeksYear('next'), $sTimes->getWeekNr('next'), 'Sa'))
                    ->setVisibleStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $sTimes->getWeekNr(), 'Do'))
                    ->setBrochureNumber('zimmermann_kw' . $sTimes->getWeekNr('next') . '_' . $siteAmount);

            $cBrochures->addElement($eBrochure);
        }

        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
