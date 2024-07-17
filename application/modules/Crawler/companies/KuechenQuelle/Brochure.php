<?php

/* 
 * Prospekt Crawler für Küchen Quelle (ID: 29227)
 */

class Crawler_Company_KuechenQuelle_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aFilesToLink = array();
        $strLocalTemplateFile = '';
        foreach ($sFtp->listFiles('./prospekte') as $singleFile) {
            $pattern = '#_([^_]+?)_ONLINE(_template)?\.pdf$#i';
            if (preg_match($pattern, $singleFile, $cityMatch)) {
                if (count($cityMatch) == 3) {
                    $strLocalTemplateFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    $aFilesToLink[preg_replace('#ue#', 'ü', ucwords(strtolower($cityMatch[1])))] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                } else {
                    $aFilesToLink[preg_replace('#ue#', 'ü', ucwords(strtolower($cityMatch[1])))] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                }
            }
        }

        $jInfos = $sPdf->getAnnotationInfos($strLocalTemplateFile);

        $cStores = $sApi->findStoresByCompany($companyId);

        $modificationData = array(
            'searchPattern' => 'PLACEHOLDER',
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cStores->getElements() as $eStore) {
            if (preg_match('#47803#', $eStore->getZipcode())) {
                continue;
            }
            if (preg_match('#template#', $aFilesToLink[$eStore->getCity()])) {
                $modificationData['replacePattern'] = $eStore->getWebsite();
                $storeJsonFile = $localPath . 'exchangeData_' . $eStore->getCity() . '.json';

                $fh = fopen($storeJsonFile, 'w+');
                fwrite($fh, json_encode(array($modificationData)));
                fclose($fh);

                $linkedStoreFile = $sPdf->modifyLinks($strLocalTemplateFile, $storeJsonFile, TRUE);
            } else {
                $aCoordsToLink[] = array(
                    'page' => '0',
                    'height' => $jInfos[0]->height,
                    'width' => $jInfos[0]->height,
                    'startX' => $jInfos[0]->rectangle->startX,
                    'endX' => $jInfos[0]->rectangle->endX,
                    'startY' => $jInfos[0]->rectangle->startY,
                    'endY' => $jInfos[0]->rectangle->endY,
                    'link' => $eStore->getWebsite()
                );

                $coordFileName = $localPath . 'coordinates_' . $companyId . '_' . $eStore->getCity() . '.json';

                $fh = fopen($coordFileName, 'w+');
                fwrite($fh, json_encode($aCoordsToLink));
                fclose($fh);

                $linkedStoreFile = $sPdf->setAnnotations($aFilesToLink[$eStore->getCity()], $coordFileName, $localPath . $eStore->getCity() . '_linked.pdf');
            }
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Monats Angebote')
                ->setUrl($sFtp->generatePublicFtpUrl($linkedStoreFile))
                ->setStart(date('d.m.Y', strtotime('first day of next month')))
                ->setEnd(date('d.m.Y', strtotime('last day of next month')))
                ->setVisibleStart($eBrochure->getStart())
                ->setStoreNumber($eStore->getStoreNumber())
                ->setVariety('leaflet')
                ->setBrochureNumber(date('Y_m', strtotime('next month')) . '_' . $eStore->getCity());

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
