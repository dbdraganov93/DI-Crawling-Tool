#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';
$sExcel = new Marktjagd_Service_Input_PhpExcel();
$sAddress = new Marktjagd_Service_Text_Address();
$sTimes = new Marktjagd_Service_Text_Times();

ini_set('memory_limit', '2G');
foreach (scandir(APPLICATION_PATH . '/../public/files/') as $singleFile) {
    if (preg_match('#VKST-Liste.+?csv#', $singleFile)) {
        $storeFile = APPLICATION_PATH . '/../public/files/' . $singleFile;
        break;
    }
}

$storeData = $sExcel->readFile($storeFile, true, ';')->getElement(0)->getData();

$cStores = new Marktjagd_Collection_Api_Store();
foreach ($storeData as $singleStore) {
    if (preg_match('#umbau#i', $singleStore['Status'])) {
        continue;
    }
    
    $eStore = new Marktjagd_Entity_Api_Store();
    $eStore->setStoreNumber('DSD' . $singleStore['VKST'])
            ->setCity($singleStore['Ort1'])
            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleStore['Strasse'])))
            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleStore['Strasse'])))
            ->setPhone($sAddress->normalizePhoneNumber($singleStore['Telefon']))
            ->setZipcode($singleStore['PLZ'])
            ->setStoreHours($sTimes->generateMjOpenings($singleStore['Öffnungszeiten']))
            ->setLatitude($singleStore['y'])
            ->setLongitude($singleStore['x']);
    
    Zend_Debug::dump('adding ' . $eStore->getStoreNumber());
    
    $cStores->addElement($eStore);
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($argv[1]);
$fileName = $sCsv->generateCsvByCollection($cStores);

echo $fileName . "\n";