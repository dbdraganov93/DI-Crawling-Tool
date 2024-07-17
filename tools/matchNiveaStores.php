#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

$sApi = new Marktjagd_Service_Input_MarktjagdApi();
$sExcel = new Marktjagd_Service_Input_PhpExcel();

$localFilePath = APPLICATION_PATH . '/../public/files/nivea.xls';
$aData = $sExcel->readFile($localFilePath, TRUE)->getElement(0)->getData();

$dataNotMatched = $sExcel->readFile($localFilePath, TRUE)->getElement(0)->getData();

$aDmStores = $sApi->findAllStoresForCompany(27);
$aRossmannStores = $sApi->findAllStoresForCompany(26);

$cStores = new Marktjagd_Collection_Api_Store();
foreach ($aData as $singleDataKey => $singleDataValue) {
    $aAddress = preg_split('#\s*,\s*#', $singleDataValue['Adresse']);
    switch ($singleDataValue['Händler']) {
        case 'dm':
            $storesToSearch = $aDmStores;
            break;
        case 'Rossmann':
            $storesToSearch = $aRossmannStores;
    }
        
    foreach ($storesToSearch as $singleStore) {
        if (preg_match('#' . $singleStore['zipcode'] . '#', $aAddress[0])
                && preg_match('#' . $singleStore['city'] . '#', $aAddress[0])
                && preg_match('#' . $singleStore['street'] . '#', $aAddress[1])
                && preg_match('#' . $singleStore['street_number'] . '#', $aAddress[1])) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($singleStore['title'])
                    ->setStreet($singleStore['street'])
                    ->setStreetNumber($singleStore['street_number'])
                    ->setZipcode($singleStore['zipcode'])
                    ->setCity($singleStore['city'])
                    ->setPhoneNormalized($singleStore['phone_number'])
                    ->setStoreHoursNormalized($singleStore['store_hours']);
            
            $cStores->addElement($eStore);
            
            unset($dataNotMatched[$singleDataKey]);
        }
    }
    
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore(71917);
$fileName = $sCsv->generateCsvByCollection($cStores);

echo "$fileName\n";

Zend_Debug::dump($dataNotMatched);