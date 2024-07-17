<?php

/**
 * Brochure crawler for DM (ID: 27)
 */
class Crawler_Company_Dm_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aData = $sGSRead->getCustomerData('dmGer_Kufi');

        $localPath = $sFtp->connect($companyId, TRUE);

        if (!$localBrochure = $sFtp->downloadFtpToDir($aData['pdfName'], $localPath)) {
            throw new Exception($companyId . ': unable to download brochure');
        }

        $sFtp->close();

        $aStoreData = $sGSRead->getFormattedInfos($aData['spreadsheetId'], 'A1', 'R', 'DM Kufi_' . date('m', strtotime($aData['validStart'])));

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $awsPath = '';
        foreach ($aStoreData as $singleStoreData) {
            if (!$awsPath) {
                $awsPath = $localBrochure;
            }
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setBrochureNumber($singleStoreData['brochure_number'])
                ->setStoreNumber(preg_replace('#\.#', '', $singleStoreData['store_number']))
                ->setTitle($aData['title'])
                ->setUrl($awsPath)
                ->setStart($aData['validStart'])
                ->setEnd($aData['validEnd'])
                ->setVisibleStart($eBrochure->getStart());

            $cBrochures->addElement($eBrochure, FALSE);

            $awsPath = $eBrochure->getUrl();
        }

        return $this->getResponse($cBrochures);
    }
}