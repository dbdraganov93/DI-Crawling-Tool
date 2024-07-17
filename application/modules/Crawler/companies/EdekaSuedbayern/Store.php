<?php

/**
 * Store Crawler für Edeka Südbayern (ID: 72089 - 72091, 72301)
 */
class Crawler_Company_EdekaSuedbayern_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $patterns = [
            '72089' => '#^Neukauf#',
            '72090' => '#^EC#',
            '72301' => '#e\s*xpress#i',
            '82395' => '#trinkgut#i'
        ];

        $columnToCheck = 'WERBEGEBIET_HZ';

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($this->getStores($companyId) as $store) {
            if (is_null($store[$columnToCheck]) || !preg_match($patterns[$companyId], $store[$columnToCheck])) {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($store['BEZEICHNUNG'])
                ->setStreetAndStreetNumber($store['STRASSE'] ?: $store['STRAßE'])
                ->setZipcode($store['PLZ'])
                ->setCity($store['ORT'])
                ->setPhoneNormalized($store['TELEFON'])
                ->setText(preg_replace('#\_x000D\_\|#', '<br/>', $store['BEMERKUNGEN']))
                ->setStoreHoursNormalized($store['STANDARD_ÖFFNUNGSZEITEN'])
                ->setStoreNumber($store['MARKT_ID'])
                ->setDistribution($store['WERBEGEBIET_HZ']);

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId, 2, false);
    }

    /**
     * @param int $companyId
     * @return array
     */
    private function getStores(int $companyId): array
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect('72089');

        foreach ($sFtp->listFiles('.', '#Marktliste_DPP\.xlsx?$#') as $singleFile) {
            $localStoreFile = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
            $stores = $sExcel->readFile($localStoreFile, true)->getElement(0)->getData();
            $sFtp->close();
            return $stores;
        }
        $sFtp->close();
        return [];
    }

    /**
     * @param array $singleStore
     * @return string
     */
    private function getStoreHours(array $singleStore): string
    {
        $strTimes = '';
        foreach (['MO', 'DI', 'MI', 'DO', 'FR', 'SA'] as $singleDay) {
            $timeBreakFrom = $this->_convertTime($singleStore[$singleDay . '_PAUSE_VON']);
            $timeBreakTill = $this->_convertTime($singleStore[$singleDay . '_PAUSE_BIS']);
            $timeFrom = $this->_convertTime($singleStore[$singleDay . '_VON']);
            $timeTill = $this->_convertTime($singleStore[$singleDay . '_BIS']);
            if (strlen($strTimes)) {
                $strTimes .= ',';
            }
            if (!preg_match('#^0:00#', $timeBreakFrom) && !preg_match('#^0:00#', $timeBreakTill)) {
                $strTimes .= ucwords(strtolower($singleDay)) . ' ' . $timeFrom . '-' . $timeBreakFrom
                    . ', ' . ucwords(strtolower($singleDay)) . ' ' . $timeBreakTill . '-' . $timeTill;
            } else {
                $strTimes .= ucwords(strtolower($singleDay)) . ' ' . $timeFrom . '-' . $timeTill;
            }
        }
        return $strTimes;
    }

    /**
     * @param $strTime
     * @return string
     */
    protected function _convertTime($strTime): string
    {
        $time = $strTime * 24 * 100;
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
