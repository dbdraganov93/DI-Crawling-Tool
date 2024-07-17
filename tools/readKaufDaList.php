#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

$sExcel = new Marktjagd_Service_Input_PhpExcel();
$sApi = new Marktjagd_Service_Input_MarktjagdApi();

$aApiCompanies = $sApi->getAllActiveCompanies();


$filePath = APPLICATION_PATH . '/../public/files/jut.csv';

$aCompanies = $sExcel->readFile($filePath, TRUE, ';')->getElement(0)->getData();

$fh = fopen(APPLICATION_PATH . '/../public/files/jut_complete.csv', 'w');

foreach ($aCompanies as &$singleCompany)
{
    foreach ($aApiCompanies as $singleCompanyId => $singleCompanyValue)
    {
        if (preg_match('#^' . normalizeTitle($singleCompany['Händler']) . '#', normalizeTitle($singleCompanyValue['title'])) || preg_match('#^' . normalizeTitle($singleCompanyValue['title']) . '#', normalizeTitle($singleCompany['Händler'])))
        {
            $singleCompany['company ID'] = (string) $singleCompanyId;
            $singleCompany['bei MJ angelegt'] = '1';
        }
    }

    fputcsv($fh, $singleCompany, ';');
}
fclose($fh);

Zend_Debug::dump(APPLICATION_PATH . '/../public/files/jut_complete.csv');

function normalizeTitle($stringIn)
{
    return preg_replace('#[^a-zäöü0-9]+#', '-', strtolower($stringIn));
}
