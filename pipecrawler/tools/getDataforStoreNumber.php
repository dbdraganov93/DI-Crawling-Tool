<?php

require_once '../scripts/index.php';
$sApi = new Marktjagd_Service_Input_MarktjagdApi();
$aStoreNumbers = array($argv[1]);

$aStores = $sApi->findStoresByCompany($argv[2]);
$cStores = new Marktjagd_Collection_Api_Store();
$count = 1;
foreach ($aStores->getElements() as $singleStore) {
    if (in_array($singleStore->getStoreNumber(), $aStoreNumbers)) {
        Zend_Debug::dump($singleStore);die;
        $cStores->addElement($singleStore);
        $aUsedNumbers[] = $singleStore->getStoreNumber();
    }
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore('22235');
$fileName = $sCsv->generateCsvByCollection($cStores);

echo $fileName . "\n";