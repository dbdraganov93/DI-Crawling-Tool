#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

$sApi = new Marktjagd_Service_Input_MarktjagdApi();

$aStores = $sApi->findStoresByCompany(68847);

$aDistribution = array(
    array('name' => 'HB',
        'amountAssignedStores' => 1209),
    array('name' => 'Corsa',
        'amountAssignedStores' => 604)
);

foreach ($aStores->getElements() as &$singleStore) {
    if (preg_match('#(Rüsselsheim|Wiesbaden)#', $singleStore->getCity())) {
        continue;
    }
    while (TRUE) {
        $key = rand(0, 1);
        if ($aDistribution[$key]['amountAssignedStores'] > 0) {
            $singleStore->setDistribution($aDistribution[$key]['name']);
            $aDistribution[$key]['amountAssignedStores'] --;
            break;
        }
    }
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore(68847);
$fileName = $sCsv->generateCsvByCollection($aStores);

echo "$fileName\n";