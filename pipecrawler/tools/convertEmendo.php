#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');

$sExcel = new Marktjagd_Service_Input_PhpExcel();

$sAddress = new Marktjagd_Service_Text_Address();
$sTimes = new Marktjagd_Service_Text_Times();
$cStores = new Marktjagd_Collection_Api_Store();


$skipStores = array(
    'EM0031',
    'EM0033',
    'EM0047',
    'EM0238',
    'EM0381',
    'EM0415'  
);

$storeData = $sExcel->readFile('/tmp/stores_emendo.xlsx', true)->getElement(0)->getData();

$cStores = new Marktjagd_Collection_Api_Store();
foreach ($storeData as $singleStore){
    $eStore = new Marktjagd_Entity_Api_Store();
    
    if (in_array($singleStore["ID-Nr."], $skipStores)){
        $logger->info('skip store ' . $singleStore["ID-Nr."]);
        continue;
        
    }
    
    $eStore->setStoreNumber($singleStore["ID-Nr."])
            ->setTitle($singleStore["Firma"])
            ->setZipcode($singleStore["Plz"])
            ->setCity($singleStore["Ort"])
            ->setStreet($sAddress->extractAddressPart('street', $singleStore["Strasse"]))
            ->setStreetNumber($sAddress->extractAddressPart('street_number', $singleStore["Strasse"]))
            ->setPhone($singleStore["Fon"])
            ->setFax($singleStore["Fax"])
            ->setStoreHours($sTimes->generateMjOpenings($singleStore["Öffnungszeiten"] . ',' . $singleStore[""]));     
    
    $cStores->addElement($eStore);          
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
$fileName = $sCsv->generateCsvByCollection($cStores);

echo  $fileName . "\n";
