<?php

/**
 * Brochure crawler for E/D/E (ID: 82392)
 */

class Crawler_Company_Ede_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cStores = $sApi->findStoresByCompany($companyId);
        foreach ($cStores->getElements() as $eStore) {
            $aStores[$eStore->getTitle()]['storenumber'][] = $eStore->getStoreNumber();
        }
        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.pdf$#', $singleRemoteFile)) {
                $localTemplateFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            } elseif (preg_match('#\.csv$#', $singleRemoteFile)) {
                $localClickoutFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        $sFtp->close();

        $aData = $sPss->readFile($localClickoutFile, TRUE, ';')->getElement(0)->getData();
        foreach ($aData as $singleRow) {
            if (!$storeNumber = implode(',', $aStores[trim(preg_replace('#\s{2,}#', ' ', $singleRow['title']))]['storenumber'])) {
                Zend_Debug::dump($singleRow['title']);
                die;
            }
            $clickoutFileName = $localPath . 'coordinates_' . preg_replace('#id:#', '', $storeNumber) . '.json';
            $fh = fopen($clickoutFileName, 'w+');
            fwrite($fh, json_encode(
                    [
                        [
                            'page' => 0,
                            'height' => 765.354,
                            'width' => 524.409,
                            'startX' => 360.072,
                            'endX' => 385.84,
                            'startY' => 687.07,
                            'endY' => 711.07,
                            'link' => $singleRow['page 1']
                        ],
                        [
                            'page' => 1,
                            'height' => 765.354,
                            'width' => 1048.82,
                            'startX' => 429.007,
                            'endX' => 453.007,
                            'startY' => 307.928,
                            'endY' => 331.928,
                            'link' => $singleRow['page 2']
                        ],
                        [
                            'page' => 2,
                            'height' => 765.354,
                            'width' => 1048.82,
                            'startX' => 439.613,
                            'endX' => 463.613,
                            'startY' => 679.116,
                            'endY' => 703.116,
                            'link' => $singleRow['page 3']
                        ],
                        [
                            'page' => 4,
                            'height' => 765.354,
                            'width' => 524.409,
                            'startX' => 300.072,
                            'endX' => 325.84,
                            'startY' => 627.07,
                            'endY' => 651.07,
                            'link' => $singleRow['page 5']
                        ]
                    ]
                )
            );
            fclose($fh);

            if (copy($localTemplateFile, preg_replace('#\.pdf#', preg_replace('#id:#', '', $storeNumber) . '.pdf', $localTemplateFile))) {
                $storeFileName = preg_replace('#\.pdf#', preg_replace('#id:#', '', $storeNumber) . '.pdf', $localTemplateFile);
            }

            if ($sPdf->setAnnotations($storeFileName, $clickoutFileName)) {
                $linkedFileName = preg_replace('#\.pdf#', '_linked.pdf', $storeFileName);
                $aStores[trim(preg_replace('#\s{2,}#', ' ', $singleRow['title']))]['filepath'] = $linkedFileName;
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aStores as $singleStore) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('BADEzeit')
                ->setUrl($singleStore['filepath'])
                ->setStoreNumber(implode(',', $singleStore['storenumber']))
                ->setStart('15.09.2022')
                ->setEnd('14.12.2022')
                ->setVisibleStart($eBrochure->getStart());

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}