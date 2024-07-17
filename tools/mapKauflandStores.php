#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

$sExcel = new Marktjagd_Service_Input_PhpExcel();
$sTimes = new Marktjagd_Service_Text_Times();

$localPath = $argv[1];

$aStores = $sExcel->readFile($localPath, TRUE)->getElement(0)->getData();

$cStores= new Marktjagd_Collection_Api_Store();
foreach ($aStores as $singleStore) {
    $eStore = new Marktjagd_Entity_Api_Store();
    
    $eStore->setStoreNumber($singleStore['Geschäftscode'])
            ->setStreetAndStreetNumber($singleStore['Adresszeile 1'])
            ->setCity($singleStore['Stadt'])
            ->setZipcode($singleStore['Postleitzahl'])
            ->setPhoneNormalized($singleStore['Primäre Telefonnummer'])
            ->setWebsite($singleStore['Website'])
            ->setStoreHours($sTimes->convertGoogleOpenings($singleStore['Öffnungszeiten']));
    
    $cStores->addElement($eStore);
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore('67394');
$fileName = $sCsv->generateCsvByCollection($cStores);

echo "$fileName\n";