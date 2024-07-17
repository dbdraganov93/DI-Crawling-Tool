<?php

/**
 * Brochure crawler for Red Zac AT (ID: 72492)
 */

class Crawler_Company_RedZacAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.redzac.at/';
        $feedUrl = $baseUrl . 'feed/offerista/';
        $pdfUrl = $feedUrl . 'flugblatt.pdf';
        $clickoutFileUrl = $feedUrl . 'flugblatt.csv';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sGSpreadsheetRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfosGSheet = $sGSpreadsheetRead->getFormattedInfos('1fDgXOh3RjKwBa0ojgHORzvmvPAl4MStJjwd5LPpwPlA', 'A1', 'F', 'redZacAt')[0];

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $ch = curl_init($pdfUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $response = curl_exec($ch);
        curl_close($ch);

        $localBrochurePath = $localPath . 'flugblatt.pdf';

        $localBrochure = fopen($localBrochurePath, "w");
        fwrite($localBrochure, $response);
        fclose($localBrochure);

        $localClickoutFile = $sHttp->getRemoteFile($clickoutFileUrl, $localPath);

        $aInfos = $sPdf->getAnnotationInfos($localBrochurePath);

        $aData = $sPss->readFile($localClickoutFile, TRUE, ';')->getElement(0)->getData();
        $aClickouts = [];
        foreach ($aData as $singleRow) {
            $pageNo = $singleRow['Flugblatt-Seite'] - 1;
            $width = $aInfos[$pageNo]->width;
            $height = $aInfos[$pageNo]->height;
            $aClickouts[] = [
                'page' => $pageNo,
                'width' => $width,
                'height' => $height,
                'startX' => $width * (preg_replace('#%#', '', $singleRow['Seitenposition-X']) / 100),
                'endX' => $width * (preg_replace('#%#', '', $singleRow['Seitenposition-X']) / 100) + 5,
                'startY' => $height - ($height * (preg_replace('#%#', '', $singleRow['Seitenposition-Y']) / 100)),
                'endY' => $height - ($height * (preg_replace('#%#', '', $singleRow['Seitenposition-Y']) / 100) + 5),
                'link' => $singleRow['Produktlink']
            ];
        }

        $coordFile = APPLICATION_PATH . '/../public/files/tmp/coordinates.json';
        $fh = fopen($coordFile, 'w+');
        fwrite($fh, json_encode($aClickouts));
        fclose($fh);

        $fileLinked = $sPdf->setAnnotations($localBrochurePath, $coordFile);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle($aInfosGSheet['title'])
            ->setUrl($fileLinked)
            ->setStart($aInfosGSheet['validityStart'])
            ->setEnd($aInfosGSheet['validityEnd'])
            ->setVisibleStart($eBrochure->getStart());

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }

}