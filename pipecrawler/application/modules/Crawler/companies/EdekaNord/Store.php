<?php

/**
 * Store Crawler für Edeka Nord (ID: 73540 - 73541)
 */
class Crawler_Company_EdekaNord_Store extends Crawler_Generic_Company
{
    private string $workingHours = '';
    private const WEEK_DAYS = [
        'MO',
        'DI',
        'MI',
        'DO',
        'FR',
        'SA'
    ];
    private const MARKET_ID = [
        '8002827',
        '10002027',
        '10002025'
    ];

    public function crawl($companyId)
    {
        $ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();

        $connectMarketFtp = $ftp->connect('73541', TRUE);

        foreach ($ftp->listFiles() as $singleFile) {
            if (preg_match('/\.csv$/', $singleFile)) {
                $downloadMarketFile = $ftp->downloadFtpToDir($singleFile, $connectMarketFtp);
            }
        }
        $ftp->close();
        $marketContents = $spreadsheetService->readFile($downloadMarketFile, TRUE)->getElement(0)->getData();

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($marketContents as $marketContent) {
            if (is_null($marketContent)) {
                continue;
            }

            if ($companyId == '73540' && !preg_match('#marktkauf#i', $marketContent['VERTRIEBSSCHIENE'])) {
                continue;
            } elseif ($companyId == '73541' && preg_match('#marktkauf#i', $marketContent['VERTRIEBSSCHIENE'])) {
                continue;
            }
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setTitle($marketContent['BEZEICHNUNG'])
                ->setStreetAndStreetNumber($marketContent['STRAßE'])
                ->setZipcode($marketContent['PLZ'])
                ->setCity($marketContent['ORT'])
                ->setPhoneNormalized($marketContent['TELEFON'])
                ->setText(preg_replace('#\_x000D\_\|#', '<br/>', $marketContent['BEMERKUNGEN']))
                ->setStoreHoursNormalized($marketContent['STANDARD_ÖFFNUNGSZEITEN'])
                ->setStoreNumber($marketContent['MARKT_ID'])
                ->setDistribution($marketContent['WERBEGEBIET_HZ'] . ',' . $marketContent['WERBEGEBIET_UHZ']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }

    protected function _convertTime($workingHours)
    {
        $time = $workingHours * 24 * 100;
        if ($time % 100 == 0) {
            $strTimeReal = $time / 100 . ':00';
        } else {
            $minutes = round(($time % 100) * 0.6);

            if ($minutes == 59) {
                $minutes = '00';
            }
            $strTimeReal = round($time / 100, 0, PHP_ROUND_HALF_DOWN) . ':' . $minutes;
        }
        return $strTimeReal;
    }

}
