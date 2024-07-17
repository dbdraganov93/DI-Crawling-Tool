#!/usr/bin/php
<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';

echo "adValidityCheck.php started.\n";

$sQC = new Marktjagd_Service_Output_QualityCheck();
$sCompany = new Marktjagd_Database_Service_Company();

$aCompanies = $sCompany->findAll();

foreach ($aCompanies as $singleCompany)
{
    if ($sQC->checkForAdValidity($singleCompany->getIdCompany()))
    {
        echo "company " . $singleCompany->getIdCompany() . " added.\n";
    }
}

echo "adValidityCheck.php finished.\n";