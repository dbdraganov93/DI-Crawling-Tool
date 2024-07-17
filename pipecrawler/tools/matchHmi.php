<?php

require_once '../scripts/index.php';

$sExcel = new Marktjagd_Service_Input_PhpExcel();
$sAddress = new Marktjagd_Service_Text_Address();
$sTimes = new Marktjagd_Service_Text_Times();

$aFiles = $sExcel->readFile(APPLICATION_PATH . '/../tools/Handzettelbesteller.xlsx', true)->getElement(0)->getData();

$cStores = new Marktjagd_Collection_Api_Store();

foreach ($aFiles as $singleFile)
{
    if (!strlen($singleFile['marktjagd'])) {
        continue;
    }
    $eStore = new Marktjagd_Entity_Api_Store();
    
    $eStore->setWebsite($singleFile['Internetadresse'])
            ->setTitle($singleFile['Firma'])
            ->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $singleFile['Straße'])))
            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $singleFile['Straße'])))
        ->setZipcode($singleFile['PLZ'])
            ->setCity($singleFile['Ort'])
            ->setPhone($sAddress->normalizePhoneNumber($singleFile['Vorwahl'] . $singleFile['Telefon']))
        ->setEmail($singleFile['email']);
    
    $cStores->addElement($eStore);
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore('71796');
$fileName = $sCsv->generateCsvByCollection($cStores);
echo $fileName . "\n";