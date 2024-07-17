#!/usr/bin/php
<?php
chdir(__DIR__);
/**
 * Findet alle aktiven Prospekte für ein Unternehmen und ermittelt die zugewiesenen Standorte inklusive Geodaten
 * Nutzung: php getStoresForBrochures.php <companyId>
 */
require_once '../scripts/index.php';
$sApi = new Marktjagd_Service_Input_MarktjagdApi();
$aBrochures = $sApi->findActiveBrochuresByCompany($argv[1]);

$filePath = APPLICATION_PATH . '/../public/files/';
// Sichergehen, dass der Ordner existiert und beschreibbar ist
if (is_writable($filePath)) {
    $fileName = $filePath . 'ProspektInfo_' . $argv[1] . '.csv';
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

if (count($aBrochures) > 0) {
    unset($aBrochures['lastModified']);
    foreach ($aBrochures as $singleKey => $singleValue) {
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
        $aStores = $sApi->findStoresWithActiveBrochures($singleKey, $argv[1]);
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
    $sHttp = new Marktjagd_Service_Transfer_Http();
    $sHttp->generatePublicHttpUrl($fileName);

} else {
    echo "Keine Standorte mit aktiven Prospekten vorhanden.\n";
}
