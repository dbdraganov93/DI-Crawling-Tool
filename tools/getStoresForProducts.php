#!/usr/bin/php
<?php
chdir(__DIR__);
/**
 * Findet alle aktiven Produkte für ein Unternehmen und ermittelt die zugewiesenen Standorte inklusive Geodaten
 * Nutzung: php getStoresForProducts.php <companyId>
 */
require_once '../scripts/index.php';
$sApi = new Marktjagd_Service_Input_MarktjagdApi();

$aProducts = $sApi->findActiveArticlesByCompany($argv[1]);
$filePath = APPLICATION_PATH . '/../public/files/export/';
// Sichergehen, dass der Ordner existiert und beschreibbar ist
if (is_writable($filePath)) {
    $fileName = $filePath . 'ProduktInfo_' . $argv[1] . '.csv';
}

$fh = fopen($fileName, 'w+');
fputcsv($fh, array(
    'Standort-ID',
    'PLZ',
    'Stadt',
    'Straße',
    'Breitengrad',
    'Längengrad'), ';');

if (count($aProducts) > 1) {
    $aStores = $sApi->findStoresWithActiveProducts($argv[1]);
    foreach ($aStores as $storeId => $storeValue) {
        $strStreet = $storeValue['street'];
        if (strlen($storeValue['street_number'])) {
            $strStreet .= ' ' . $storeValue['street_number'];
        }
        fputcsv($fh, array(
            $storeId,
            $storeValue['zipcode'],
            $storeValue['city'],
            $strStreet,
            $storeValue['lat'],
            $storeValue['lng']), ';');
    }
    fclose($fh);
    
    echo $fileName . "\n";
} else {
    echo "Keine Standorte mit aktiven Produkten vorhanden.\n";
}
