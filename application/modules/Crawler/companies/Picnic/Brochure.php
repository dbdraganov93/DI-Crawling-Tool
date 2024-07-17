<?php

/**
 * Brochure-Crawler für Picnic (ID: 82423)
 */
class Crawler_Company_Picnic_Brochure extends Crawler_Generic_Company
{
    private const SHEET_ID = '1kTmUQp0PIfOJtevcOoKjBBfUY2C6v6C3-cA47gX9DX8';
    private const SHEET_NAME = 'Tabellenblatt1';
    private const START_DATE = '07.11.2022';
    private const END_DATE = '13.11.2022';

    public function crawl($companyId)
    {
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $week = $sTimes->getWeekNr('this');
        $localBrochurePath = '';

        $googleRawData = $sGS->getFormattedInfos(self::SHEET_ID, 'A1', 'B', self::SHEET_NAME);
        $filteredPlzSheetData = [];
        foreach ($googleRawData as $singleSheetArray) {
            if (!in_array(array_keys($singleSheetArray)[0], $filteredPlzSheetData)) {
                $filteredPlzSheetData[] = array_keys($singleSheetArray)[0];
            }
            $filteredPlzSheetData[] = (int) array_values($singleSheetArray)[0];
        }

        $localPath = $sFtp->connect('82423', true);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#[^\.]+'.$week.'\.pdf#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        $sFtp->close();

        $sHttp = new Marktjagd_Service_Transfer_Http();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('Picnic: Dein neuer Lieferservice')
            ->setBrochureNumber('KW' . $week)
            ->setUrl($sHttp->generatePublicHttpUrl($localBrochurePath))
            ->setVariety('leaflet')
            ->setStart(self::START_DATE)
            ->setEnd(self::END_DATE)
            ->setVisibleStart($eBrochure->getStart())
            ->setZipCode(implode(', ', $filteredPlzSheetData))
        ;

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }
}
