#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

if (count($argv) < 4) {
    echo "invalid argument count! <companyId target company> <companyId company to check> <distance>\n";
    exit(1);
}

$sApi = new Marktjagd_Service_Input_MarktjagdApi();
$sGeo = new Marktjagd_Service_Text_Address();

$aTargetStores = preg_split('#\s*,\s*#', $argv[1]);
$aToCheckStores = preg_split('#\s*,\s*#', $argv[2]);

$aStoresTarget = array();
$aStoresToCheck = array();
foreach ($aTargetStores as $singleTargetStore) {
    $aStoresTarget = array_merge($aStoresTarget, $sApi->findStoresByCompany($singleTargetStore)->getElements()); // Unternehmensstandorte, die als Ausgangspunkte dienen
}

foreach ($aToCheckStores as $singleToCheckStore) {
    $aStoresToCheck = array_merge($aStoresToCheck, $sApi->findStoresByCompany($singleToCheckStore)->getElements()); // Unternehmensstandorte, die sich im spezifischen Radius um Zielstandorte befinden sollen
}


$cStores = new Marktjagd_Collection_Api_Store();
foreach ($aStoresToCheck as $singleStoreToCheck) {
    foreach ($aStoresTarget as $singleStoreTarget) {
        $distance = round($sGeo->calculateDistanceFromGeoCoordinates($singleStoreToCheck->getLatitude(), $singleStoreToCheck->getLongitude(), $singleStoreTarget->getLatitude(), $singleStoreTarget->getLongitude()), 2);
        if ($distance <= $argv[3]) {
            $singleStoreToCheck->setText('Entfernung zu ' . $singleStoreTarget->getTitle() . ' - Standort [' . $singleStoreTarget->getStoreNumber() . ']: ' . $distance . ' km.');
            $cStores->addElement($singleStoreToCheck);
        }
    }
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore('calculatedDistances');
$fileName = $sCsv->generateCsvByCollection($cStores);

echo "$fileName\n";