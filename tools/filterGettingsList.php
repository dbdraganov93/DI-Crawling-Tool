#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

$sExcel = new Marktjagd_Service_Input_PhpExcel();
$sApi = new Marktjagd_Service_Input_MarktjagdApi();

$companyListPath = APPLICATION_PATH . '/../public/files/gettings.xls';

$companyData = $sExcel->readFile($companyListPath, TRUE)->getElement(0)->getData();
$coreCompanyData = $sApi->getAllActiveCompanies();

$cStores = new Marktjagd_Collection_Api_Store();
foreach ($companyData as $singleCompanyData) {
    $existing = FALSE;
    $dataUrl = preg_replace(array('#\.#', '#\/#'), array('\.', '\/'),  $singleCompanyData['Internet']);
    foreach ($coreCompanyData as $singleCoreCompany) {
        if (is_null($singleCoreCompany['url'])) {
            continue;
        }
        $coreUrl = preg_replace('#https?:\/\/#', '', $singleCoreCompany['url']);
        if (levenshtein($singleCompanyData['Internet'], $coreUrl) < 4) {
            $existing = TRUE;
            break;
        }
    }

    if (!$existing) {
        if (!preg_match('#^(\d{2,})#', $singleCompanyData['Standorte'], $amountMatch)
                || !preg_match('#\d{5}#', $singleCompanyData['PLZ'])) {
            continue;
        }
        
        if ((int)$amountMatch[1] < 50) {
            continue;
        }
        
        $eStore = new Marktjagd_Entity_Api_Store();
        $eStore->setTitle($singleCompanyData['Firma'])
                ->setStreetAndStreetNumber($singleCompanyData['Straße'])
                ->setZipcode($singleCompanyData['PLZ'])
                ->setCity($singleCompanyData['Ort'])
                ->setText($singleCompanyData['Standorte'])
                ->setSubtitle($singleCompanyData['Branche'])
                ->setWebsite($singleCompanyData['Internet']);
        
        $cStores->addElement($eStore);
    }
}

$sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore('gettings');
$fileName = $sCsv->generateCsvByCollection($cStores);
echo "$fileName\n";