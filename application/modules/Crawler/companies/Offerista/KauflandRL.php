<?php
/**
 * Finds all active broshures for Kaufland and attaches the assigned stores + their geo coordinates
 * exports the result as csv onto our ftp server - folder "KauflandRL"
 */

class Crawler_Company_Offerista_KauflandRL extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aBrochures = $sApi->findActiveBrochuresByCompany(67394);

        $sTimes = new Marktjagd_Service_Text_Times();
        $week = 'this';
        $nextWeek = $sTimes->getWeekNr($week);


        $filePath = APPLICATION_PATH . '/../public/files/';
        // Sichergehen, dass der Ordner existiert und beschreibbar ist
        if (is_writable($filePath)) {
            $fileName = $filePath . "ProspektInfo_67394_KW{$nextWeek}.csv";
        }

        $fh = fopen($fileName, 'w+');
        fputcsv($fh, array(
            'Prospekt-ID',
            'Start Sichtbarkeit',
            'Ende Sichtbarkeit',
            'Start Gültigkeit',
            'Ende Gültigkeit',
            'Standort-ID',
            'PLZ',
            'Stadt',
            'Straße',
            'Breitengrad',
            'Längengrad',
            'Standortnummer'), ';');

        unset($aBrochures['lastModified']);
        foreach ($aBrochures as $singleKey => $singleValue) {
            if(!preg_match("#D{$nextWeek}#",$singleValue['brochureNumber']) || preg_match('#MoMi#',$singleValue['brochureNumber']))
                continue;

            if (!is_null($singleValue['visibleFrom'])) {
                $singleValue['visibleFrom'] = date('d.m.Y H:i:s', strtotime($singleValue['visibleFrom']));
            }
            if (!is_null($singleValue['visibleTo'])) {
                $singleValue['visibleTo'] = date('d.m.Y H:i:s', strtotime($singleValue['visibleTo']));
            }
            if (!is_null($singleValue['validFrom'])) {
                $singleValue['validFrom'] = date('d.m.Y H:i:s', strtotime($singleValue['validFrom']));
            }
            if (!is_null($singleValue['validTo'])) {
                $singleValue['validTo'] = date('d.m.Y H:i:s', strtotime($singleValue['validTo']));
            }
            $aStores = $sApi->findStoresWithActiveBrochures($singleKey, 67394);
            foreach ($aStores as $storeId => $storeValue) {
                $strStreet = $storeValue['street'];
                if (strlen($storeValue['street_number'])) {
                    $strStreet .= ' ' . $storeValue['street_number'];
                }
                fputcsv($fh, array(
                    $singleKey,
                    $singleValue['visibleFrom'],
                    $singleValue['visibleTo'],
                    $singleValue['validFrom'],
                    $singleValue['validTo'],
                    $storeId,
                    $storeValue['zipcode'],
                    $storeValue['city'],
                    $strStreet,
                    $storeValue['lat'],
                    $storeValue['lng'],
                    $storeValue['number']), ';');
            }
        }

        fclose($fh);

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect('KauflandRL');
        $sFtp->upload($fileName, './' . basename($fileName));

        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT)
            ->setIsImport(false);

        return $this->_response;
    }
}