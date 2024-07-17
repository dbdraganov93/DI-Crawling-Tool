<?php

/**
 * Prospekt Crawler für Hornbach (ID: 60)
 */
class Crawler_Company_Hornbach_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sGSpreadsheetRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfosGSheet = $sGSpreadsheetRead->getFormattedInfos('1fDgXOh3RjKwBa0ojgHORzvmvPAl4MStJjwd5LPpwPlA', 'A1', 'F', 'hornbachGer')[0];

        $localPath = $sFtp->connect($companyId, TRUE);
        $aBrochures = [];
        $aSitesForAll = [];
        $template = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Vorlage_linked\.pdf#', $singleFile)) {
                $template = $sFtp->downloadFtpToDir($singleFile, $localPath);
            } elseif (preg_match('#DE_(01|16)_(\d{3})\.pdf#', $singleFile, $storeNumberMatch)) {
                $aBrochures[$storeNumberMatch[2]][(int)$storeNumberMatch[1]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            } elseif (preg_match('#DE_(\d{2})(_DRI)?#', $singleFile, $pageMatch)) {
                $aSitesForAll[(int)$pageMatch[1] . $pageMatch[2]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            } elseif (preg_match('#Werbegebiet[^.]*\.xlsx$#', $singleFile)) {
                $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            } elseif (preg_match('#Versionen[^.]*\.xlsx$#', $singleFile)) {
                $localSpecialFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $aReplaceData = [
            [
                'searchPattern' => 'marktguru',
                'replacePattern' => 'offerista'
            ]
        ];

        $jsonFile = APPLICATION_PATH . '/../public/files/60.json';
        $fh = fopen($jsonFile, 'w+');
        fwrite($fh, json_encode($aReplaceData));
        fclose($fh);

        $template = $sPdf->modifyLinks($template, $jsonFile);

        $aData = $sPss->readFile($localAssignmentFile)->getElement(0)->getData();
        $aAssignment = [];
        foreach ($aData as $singleRow) {
            if (!is_int($singleRow[2])) {
                continue;
            }
            $fieldNo = NULL;
            foreach ($singleRow as $key => $singleValue) {
                if (preg_match('#\d{4,5}#', $singleValue)) {
                    $fieldNo = $key;
                    break;
                }
            }
            $aAssignment[$singleRow[2]][] = str_pad($singleRow[$fieldNo], 5, '0', STR_PAD_LEFT);
        }

        $aSpecialData = $sPss->readFile($localSpecialFile, TRUE)->getElement(0)->getData();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochures as $storeNumber => $extraPages) {
            foreach ($aSitesForAll as $pageNo => $pagePath) {
                if (preg_match('#_DRI#', $pageNo)
                    && !preg_match('#DRI#', $aSpecialData[0][$storeNumber])) {
                    continue;
                }
                $extraPages[$pageNo] = $pagePath;
            }

            ksort($extraPages);
            foreach ($extraPages as $siteNo => $sitePath) {
                if (preg_match('#_DRI#', $siteNo)) {
                    unset($extraPages[(int)preg_replace('#_DRI#', '', $siteNo)]);
                }
            }

            $pdfPath = $sPdf->merge($extraPages, $localPath);
            $pdfPaths = $sPdf->copyLinks($template, [$pdfPath]);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($pdfPaths[0])
                ->setBrochureNumber($storeNumber . '_' . date('m') . '_' . date('Y'))
                ->setTitle('Hornbach: ' . $aInfosGSheet['title'])
                ->setStoreNumber($storeNumber)
                ->setStart($aInfosGSheet['validStart'])
                ->setEnd($aInfosGSheet['validEnd'])
                ->setVisibleStart($eBrochure->getStart());

            if (array_key_exists($storeNumber, $aAssignment)) {
                $eBrochure->setZipCode(implode(',', $aAssignment[$storeNumber]));
            }

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }

}
