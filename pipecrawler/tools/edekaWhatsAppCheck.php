#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

$companyIds = [$argv[1]];
$weekToCheck = $argv[2];

if (preg_match('#all#', $argv[1])) {
    $companyIds = [
        69469,
        69470,
        69471,
        71668,
        72089,
        72090
    ];
}

$sApi = new Marktjagd_Service_Input_MarktjagdApi();
foreach ($companyIds as $companyId) {
    $cStores = $sApi->findStoresByCompany($companyId);
    $aWhatsAppStores = [];
    foreach ($cStores->getElements() as $eStore) {
        if (preg_match('#WhatsApp#i', $eStore->getDistribution())) {
            $aWhatsAppStores[$eStore->getStoreNumber()] = $eStore->getStoreNumber();
        }
    }

    $aBrochures = $sApi->findActiveBrochuresByCompany($companyId);
    foreach ($aBrochures as $singleBrochureKey => $singleBrochureValues) {
        if (strtotime($singleBrochureValues['visibleFrom']) <= strtotime('now')) {
            continue;
        }
        if (preg_match('#(^(WA_)|(_WA)$)#', $singleBrochureValues['brochureNumber'])) {
            $aStores = $sApi->findStoresWithActiveBrochures($singleBrochureKey, $companyId);
            foreach ($aStores as $singleStore) {
                $aWhatsAppStoresWithBrochures[$singleStore['number']] = $singleStore['number'];
            }
        }
    }
    $aDiff = array_diff($aWhatsAppStores, $aWhatsAppStoresWithBrochures);

    if (count($aDiff)) {
        Zend_Debug::dump($companyId . ":\n" . implode("\n", $aDiff));
    }
}