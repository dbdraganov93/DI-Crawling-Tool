#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

$fileName = '/home/frank.wehder/crawler_dev/application/../public/files/kaufDaParfumeries.csv';

$sApi = new Marktjagd_Database_Service_Company();
$aCompanies = $sApi->findAll();

$fh = fopen($fileName, 'r');
$aHeader = array();
$aData = array();
while (($data = fgetcsv($fh, 0, ';')) != FALSE) {

    $aData[] = trim($data[0]);
}
foreach ($aCompanies as $singleCompany) {
    foreach ($aData as $singleDataKey => $singleData) {
        $aCompanyName = preg_split('#\s+#', strtolower(trim($singleCompany->name)));
        $aDataName = preg_split('#\s+#', strtolower(trim($singleData)));
        foreach ($aCompanyName as $singleCompanyName) {
            foreach ($aDataName as $singleDataName) {
                similar_text($singleCompanyName, $singleDataName, $percent);
                similar_text($singleDataName, $singleCompanyName, $percentReverse);
                if ($percent >= 95 && $percentReverse >= 95 || preg_match('#sparkasse#i', $singleData)) {
                    unset($aData[$singleDataKey]);
                }
            }
        }
    }
}

$fh = fopen(APPLICATION_PATH . '/../public/files/comparedKaufDaCompanies.csv', 'w');

fputcsv($fh, array(
    'name'), ';');

foreach ($aData as $singleCompany) {
    fputcsv($fh, array(
    $singleCompany), ';');
}

fclose($fh);

Zend_Debug::dump(APPLICATION_PATH . '/../public/files/comparedKaufDaCompanies.csv');