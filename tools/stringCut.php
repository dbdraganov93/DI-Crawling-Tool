#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';
$sCsv = new Marktjagd_Service_Input_MarktjagdCsv();
$cStores = $sCsv->convertToCollection(APPLICATION_PATH . '/../tools/stores.csv', 'stores')->getElements();
$cStoresNew = new Marktjagd_Collection_Api_Store();
foreach ($cStores as $eStore) {
    if (strlen($eStore->getPhone()) > 15) {
        $eStore->setPhone(substr($eStore->getPhone(), 0, (strlen($eStore->getPhone()) / 2 + 2)));
    }
    $cStoresNew->addElement($eStore);
}

$sCsvOut = new Marktjagd_Service_Output_MarktjagdCsvStore('67475');
Zend_Debug::dump($sCsvOut->generateCsvByCollection($cStoresNew));