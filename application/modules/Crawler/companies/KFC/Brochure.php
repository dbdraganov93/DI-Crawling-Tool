<?php
/**
 * Brochure Crawler for KFC (ID: 29027)
 */

class Crawler_Company_KFC_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $aInfos = $sGSRead->getCustomerData('KFCGer');

        $localPath = $sFtp->connect($companyId, TRUE);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#KFC([^.]+?)\.pdf$#', $singleFile, $nameMatch)) {
                $localBrochures[$singleFile] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        $aStoreInfos = $sGSRead->getFormattedInfos($aInfos['spreadsheetId'], 'A1', 'Z', $aInfos['tab_name']);
        $aAssignment = [];
        foreach ($aStoreInfos as $singleStore) {
            $aAssignment[$singleStore['City/Area']][] = $singleStore['Nr'];
        }

        $aCoordinates[] = [
            'page' => 0,
            'startX' => 45.0,
            'endX' => 90.0,
            'startY' => 90.0,
            'endY' => 135.0,
            'link' => $aInfos['clickout_url']
        ];

        $coordFileName = $localPath . 'coordinates_' . $companyId . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordinates));
        fclose($fh);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aAssignment as $city => $aStoreNumbers) {
            foreach ($localBrochures as $version => $localPath) {
                if (copy($localPath, preg_replace('#\.pdf#', '_' . $city . '.pdf', $localPath))) {
                    $filePath = preg_replace('#\.pdf#', '_' . $city . '.pdf', $localPath);
                    $filePath = $sPdf->setAnnotations($filePath, $coordFileName);
                }
                $version = preg_replace('#.+Version(\d+)\.pdf#', 'V$1', $version);

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setBrochureNumber($version . '_' . $city . '_' . $aInfos[$version . '_valid_start'])
                    ->setTitle('Kentucky Fried Chicken: Free Delivery')
                    ->setUrl($filePath)
                    ->setStart($aInfos[$version . '_valid_start'])
                    ->setEnd($aInfos[$version . '_valid_end'])
                    ->setVisibleStart($eBrochure->getStart())
                    ->setStoreNumber(implode(',', $aStoreNumbers));

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures);
    }
}