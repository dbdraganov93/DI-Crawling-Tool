#!/usr/bin/php
<?php
chdir(__DIR__);
/**
 * Findet alle Standorte eines Unternehmens inklusive Geodaten
 * Nutzung: php getGeoDataForStore.php <companyId>
 */
require_once '../scripts/index.php';
$sApi = new Marktjagd_Service_Input_MarktjagdApi();
$aStores = $sApi->findStoresByCompany($argv[1],5000);

if ($aStores->getElements()) {
foreach ($aStores->getElements() as $singleStore) {
    $stores[$singleStore->getId()]['zip'] = $singleStore->getZipcode();
    $stores[$singleStore->getId()]['city'] = $singleStore->getCity();
    $stores[$singleStore->getId()]['street'] = $singleStore->getStreet() . ' ' . $singleStore->getStreetNumber();
    $stores[$singleStore->getId()]['longitude'] = $singleStore->getLongitude();
    $stores[$singleStore->getId()]['latitude'] = $singleStore->getLatitude();
}

$filePath = APPLICATION_PATH . '/../public/files/export/';
        // Sichergehen, dass der Ordner existiert und beschreibbar ist
        if (is_writable($filePath)) {
            $fileName = $filePath . 'storeInfo_' . $argv[1] . '.csv';
        }
        
$fh = fopen($fileName, 'w+');
fputcsv($fh, array(
    'storeId',
    'zip',
    'city',
    'street',
    'latitude',
    'longitude'), ';');

foreach ($stores as $storeKey => $storeValue) {
    fputcsv($fh, array(
        $storeKey,
        $storeValue['zip'],
        $storeValue['city'],
        $storeValue['street'],
        $storeValue['latitude'],
        $storeValue['longitude']), ';');
}
fclose($fh);

echo $fileName . "\n";

} else {
    echo "Keine Standorte vorhanden.\n";
}