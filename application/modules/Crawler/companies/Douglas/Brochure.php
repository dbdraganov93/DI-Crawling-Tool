<?php

/**
 * Brochure crawler for Douglas (ID: 326)
 */

class Crawler_Company_Douglas_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $aInfo = $sGSRead->getCustomerData('DouglasGer');

        $localPath = $sFtp->connect($companyId, TRUE);

        if (!$localBrochure = $sFtp->downloadFtpToDir('ET02-01_Postwurf_SkincareWeeks_DE.pdf', $localPath)) {
            throw new Exception($companyId . ': unable to download brochure');
        }

        $sFtp->close();

        $aZipcodeData = $sGSRead->getFormattedInfos('1dtf09C0GWPlh2Rd_si1ci9Sc_OdKqeYu0zgrGJ0uAoU', 'A1', 'B', 'Zipcode');
        foreach ($aZipcodeData as $singleRow) {
            if (!$singleRow['Zipcodes']) {
                break;
            }
            $aZipcodes[] = str_pad($singleRow['Zipcodes'], 5, '0', STR_PAD_LEFT);
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($localBrochure)
            ->setBrochureNumber($aInfo['brochureNumber'])
            ->setStart($aInfo['validityStart'])
            ->setEnd($aInfo['validityEnd'])
            ->setVisibleStart($eBrochure->getStart())
            ->setTitle('Douglas: Skincare Weeks')
            ->setZipCode(implode(',', $aZipcodes));

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }
}