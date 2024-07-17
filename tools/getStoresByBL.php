<?php

require_once '../scripts/index.php';

$mjApi = new Marktjagd_Service_Input_MarktjagdApi();
$mjRegion = new Marktjagd_Database_Service_GeoRegion();

$stores = $mjApi->findStoresByCompany('351');

$storesStates = array('SN','TH','ST');

$stores1 = array();
$stores2 = array();


foreach ($stores->getElements() as $store){
    $region = $mjRegion->findShortRegionByZipCode($store->getZipcode()); 

    if (in_array($region, $storesStates)){
        $stores1[] = $store->getStoreNumber();
    } else {
        $stores2[] = $store->getStoreNumber();;
    }
}

echo "VB 1: " . implode(',', $stores1) . "\n\n";
echo "VB 2: " . implode(',', $stores2) . "\n";









