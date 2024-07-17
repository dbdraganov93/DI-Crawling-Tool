#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

$logger = Zend_Registry::get('logger');
$sApi = new Marktjagd_Service_Input_MarktjagdApi();
$sDbSettings = new Marktjagd_Database_Service_QualityCheckCompanyInfos();

$dateToCheck = date('Y-m-d H:i:m');

foreach ($sDbSettings->findCompaniesWithBrochureCheck() as $singleCompany) {
    $allStoresAmount = count($sApi->findAllStoresForCompany($singleCompany->getIdCompany()));
    if ($allStoresAmount == 0) {
        continue;
    }

    $eCompaniesWithAdMaterial = new Marktjagd_Database_Entity_CompaniesWithAdMaterial();
    $eCompaniesWithAdMaterial->setIdCompany($singleCompany->getIdCompany())
            ->setPercentageStoresWithBrochures(round(count($sApi->findStoresWithBrochures($singleCompany->getIdCompany())) / $allStoresAmount * 100, 2))
            ->setLastTimeChecked($dateToCheck);

    $eCompaniesWithAdMaterial->save();
    $logger->log($singleCompany->getIdCompany() . ' ' . 'saved.', Zend_LOG::INFO);
}
