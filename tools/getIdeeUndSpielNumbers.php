<?php

require_once '../scripts/index.php';
$sCsv = new Marktjagd_Service_Input_MarktjagdApi();
$sExcel = new Marktjagd_Service_Input_PhpExcel();
$sAddress = new Marktjagd_Service_Text_Address();

$cStores = $sCsv->findStoresByCompany('22235')->getElements();

$file = APPLICATION_PATH . '/../tools/finale_Händlerliste_Zwischenspurt_04_11_14.xlsx';

$aStores = $sExcel->readFile($file, true)->getElement(0)->getData();

$aStoreNumbers = array();
$aUsedStores = array();
$aUnfoundStores = array();
for ($i = 0; $i < count($aStores); $i++) {
    foreach ($cStores as $singleUVStore) {
        if (($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aStores[$i]['Besteller-Straße']))
                        == $sAddress->normalizeStreet($singleUVStore->getStreet()))
                        && ($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aStores[$i]['Besteller-Straße']))
                                == $sAddress->normalizeStreetNumber((string)$singleUVStore->getStreetNumber()))
                        && (trim($aStores[$i]['PLZ']) == trim($singleUVStore->getZipCode()))) {
            $aStoreNumbers[] = $singleUVStore->getStoreNumber();
            $aUsedStores[] = $aStores[$i];
        }
//        if (($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aStores[$i]['Besteller-Straße']))
//                        == $sAddress->normalizeStreet($singleUVStore->getStreet()))
//                        && (trim($aStores[$i]['PLZ']) == trim($singleUVStore->getZipCode()))
//                && !in_array($singleUVStore->getStoreNumber(), $aStoreNumbers)) {
//            $aStoreNumbers[] = $singleUVStore->getStoreNumber();
//            $aUsedStores[] = $aStores[$i];
//        }
    }
}
Zend_Debug::dump(implode(',', $aStoreNumbers));
