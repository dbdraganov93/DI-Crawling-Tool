#!/usr/bin/php
<?php
chdir(__DIR__);
/**
 * Findet alle aktiven Prospekte für ein Unternehmen und ermittelt die prozentuale Standortabdeckung
 * Nutzung: php getStoresForBrochures.php 
 */
require_once '../scripts/index.php';
$sDb = new Marktjagd_Database_Service_CompaniesWithAdMaterial();

$idCompany = NULL;
if ($argc == 4) {
    $idCompany = $argv[3];
}

$aStoresWithBrochures = $sDb->findCompletenessByTimeSpan($argv[1], $argv[2], $idCompany);

$filePath = APPLICATION_PATH . '/../public/files/';
// Sichergehen, dass der Ordner existiert und beschreibbar ist
if (is_writable($filePath)) {
    $fileName = $filePath . 'storesAssignedInfo_' . date('YmdHim') . '.csv';
}

if (count($aStoresWithBrochures) > 1) {
$fh = fopen($fileName, 'w+');
fputcsv($fh, array(
    'Company-ID',
    'geprüfter Tag',
    'Prozent zugewiesener Prospekte',
    'Prozent zugewiesener Produkte'
        ), ';');
foreach ($aStoresWithBrochures as $singleStore) {
    fputcsv($fh, array(
    $singleStore->getIdCompany(),
    date('d.m.Y', strtotime($singleStore->getLastTimeChecked())),
    $singleStore->getPercentageStoresWithBrochures(),
        $singleStore->getPercentageStoresWithProducts()
        ), ';');
}
    fclose($fh);

    echo $fileName . "\n";
} else {
    echo "Keine Prospekte vorhanden.\n";
}