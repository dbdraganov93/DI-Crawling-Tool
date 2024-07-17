<?php
/**
 * Brochure Crawler für Küche & Co (ID: 28508)
 */

class Crawler_Company_KuecheCo_Brochure extends Crawler_Generic_Company
{
    public $_not_included = [];

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $localPath = $sFtp->connect($companyId, TRUE);

        $fileList = $sFtp->listFiles();
        foreach ($fileList as $key => $singleFile) {
            if (preg_match('#Archive#', $singleFile)) {
                unset($fileList[$key]);
            } elseif (preg_match('#V2\.pdf$#', $singleFile)) {
                $templateFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                unset($fileList[$key]);
            } elseif (preg_match('#\.xlsx?$#', $singleFile)) {
                $assignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                unset($fileList[$key]);
            }
        }

        $aImages = [];
        foreach ($fileList as $singleFolder) {
            foreach ($sFtp->listFiles($singleFolder, '#\.jpg$#', true) as $singleFile) {
                $keyRaw = preg_replace(['#\.jpg#', '#_#', '#sservice#i', '#egeraete#i', '#adresse#i'], '', basename($singleFile));
                $key = trim(strtolower($keyRaw));
                $aImages[$key] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }
        ksort($aImages);
        //Zend_Debug::dump($aImages);die;
        $sFtp->close();

        $aAnnotInfos = $sPdf->getAnnotationInfos($templateFile);

        $aAssignmentData = $sPss->readFile($assignmentFile, TRUE)->getElement(0)->getData();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aAssignmentData as $singleColumn) {
            if (preg_match('#Offenbach#', $singleColumn['Studioname '])) {
                continue;
            }

            $cleanName = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $singleColumn['Studioname ']);
            $templateNameStudio = preg_replace(['#([^\.]+?)\.pdf#', '#\s+#'], ['$1_' . $cleanName . '.pdf', ''], $templateFile);
            copy($templateFile, $templateNameStudio);

            $aElements = [];
            $aClickouts = [];
            foreach ($aAnnotInfos as $singleAnnot) {
                $aImagesKey = trim(strtolower(preg_replace(
                    ['#\s+#', '#-#', '#ä#', '#ö#', '#ü#', '#ß#', '#\daInhalt#'],
                    ['', '', 'ae', 'oe', 'ue', 'ss', ''],
                    $singleColumn[$singleAnnot->url])));
                if ($aImages[$aImagesKey]) {
                    $aElements[] = [
                        'page' => $singleAnnot->page,
                        'startX' => $singleAnnot->rectangle->startX,
                        'startY' => $singleAnnot->rectangle->startY,
                        'endX' => $singleAnnot->rectangle->endX,
                        'endY' => $singleAnnot->rectangle->endY,
                        'type' => 'image',
                        'path' => $aImages[$aImagesKey],
                        'scaling' => TRUE
                    ];

                    if (preg_match('#Pos\.\s*(\d+)#', $singleAnnot->url, $annotNumberMatch)) {
                        foreach ($singleColumn as $key => $value) {
                            if (preg_match('#Landingpage\s+Pos\.\s*' . $annotNumberMatch[1] . '$#', $key)) {
                                $aClickouts[] = [
                                    "page" => $singleAnnot->page,
                                    "height" => $singleAnnot->height,
                                    "width" => $singleAnnot->width,
                                    "startX" => $singleAnnot->rectangle->startX,
                                    "startY" => $singleAnnot->rectangle->startY,
                                    "endX" => $singleAnnot->rectangle->endX,
                                    "endY" => $singleAnnot->rectangle->endY,
                                    "link" => $value
                                ];
                            }
                        }
                    }
                }
            }

            $baseFilePath = APPLICATION_PATH . '/../public/files/tmp/' . strtolower(preg_replace(
                    '#\s+#', '', $singleColumn['Studioname ']));
            $jsonFilePath = $baseFilePath . '.json';

            $fh = fopen($jsonFilePath, 'w+');
            fwrite($fh, json_encode($aElements));
            fclose($fh);
            $this->_logger->info($companyId . ': adding elements.');
            $fileNameInserted = $sPdf->addElements(
                $templateNameStudio,
                $jsonFilePath);
            $this->_logger->info($companyId . ':  elements added.');

            $this->_logger->info($companyId . ': cleaning annotations.');
            $sPdf->cleanAnnotations($fileNameInserted);
            $this->_logger->info($companyId . ': annotations cleaned.');

            $coFileName = $baseFilePath . '_cos.json';
            $fh = fopen($coFileName, 'w+');
            fwrite($fh, json_encode($aClickouts));
            fclose($fh);

            $this->_logger->info($companyId . ': setting annotations.');
            $fileNameInserted = $sPdf->setAnnotations(
                $fileNameInserted,
                $coFileName);
            $this->_logger->info($companyId . ': annotations set.');

            $eStore = $this->getStore($cStores, $singleColumn);
            if (!$eStore) {
                $this->_not_included[] = $singleColumn['Postleitzahl '] . ' ' . $singleColumn['Studioname '];
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Aktionsangebote ' . $eStore->getTitle())
                ->setStoreNumber(strval($eStore->getStoreNumber()))
                ->setUrl($fileNameInserted)
                ->setVariety('leaflet')
                ->setEnd('30.06.2019');

            $cBrochures->addElement($eBrochure);
        }
        Zend_Debug::dump($this->_not_included);
        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param array $cStores
     * @param array $singleColumn
     * @return Marktjagd_Entity_Api_Store | null
     */
    private function getStore(&$cStores, $singleColumn)
    {
        foreach ($cStores as $key => $eStore) {
            $title = '';
            if (preg_match('#^[^\s+]+\s+(.+)#', $eStore->getTitle(), $titleRaw)) {
                if (preg_match_all('#\w+#', $titleRaw[1], $titles)) {
                    $title = implode(' ', $titles[0]);
                }
            }
            if ($eStore->getZipcode() != $singleColumn['Postleitzahl '] &&
                $title != str_replace('-', ' ', $singleColumn['Studioname '])) {
                continue;
            }
            unset($cStores[$key]);
            return $eStore;
        }
        return null;
    }
}
