#!/usr/bin/php
<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';

$sPage = new Marktjagd_Service_Input_Page();
$sGeoDb = new Marktjagd_Database_Service_GeoRegion();
$sAddress = new Marktjagd_Service_Text_Address();

for ($counter = 0; $counter <= 25; $counter++) {
    $url = 'https://www.edeka.de/search.xml?'
            // Auswahlparameter für Abfrage
            . 'fl=marktID_tlc%2Cplz_tlc%2Cort_tlc%2Cstrasse_tlc%2Cname_tlc%2C'
            . 'geoLat_doubleField_d%2CgeoLng_doubleField_d%2Ctelefon_tlc%2Cfax_tlc%2C'
            . 'services_tlc%2Coeffnungszeiten_tlc%2CknzUseUrlHomepage_tlc%2C'
            . 'urlHomepage_tlc%2CurlExtern_tlc%2CmarktTypName_tlc%2CmapsBildURL_tlc%2C'
            . 'vertriebsschieneName_tlc%2CvertriebsschieneKey_tlc'
            // restliche Parameter
            . '&hl=true&indent=off&q=indexName%3Ab2c'
            . 'MarktDBIndex%20AND%20plz_tlc%3A'
            . $counter
            . '*%20AND%20kanalKuerzel_tlcm%3Aedeka+AND+geoLat_doubleField_d%3A%5B40+TO+60'
            . '%5D+AND+geoLng_doubleField_d%3A%5B5+TO+20%5D&rows=10000';

    $aStoreListUrl[] = $url;
}

$aFranchise = array();
foreach ($aStoreListUrl as $singleUrl) {
    $sPage->open($singleUrl);
    $jStores = $sPage->getPage()->getResponseAsJson()->response->docs;

    foreach ($jStores as $singleJStore) {
        if (preg_match('#regie#i', $singleJStore->marktTypName_tlc)) {
            continue;
        }

        if (!array_key_exists($singleJStore->name_tlc, $aFranchise)) {
            $aFranchise[$singleJStore->name_tlc]['count'] = 1;
            $aFranchise[$singleJStore->name_tlc]['url'] = $singleJStore->urlExtern_tlc;
            $aFranchise[$singleJStore->name_tlc]['phone'] = $sAddress->normalizePhoneNumber($singleJStore->telefon_tlc);
        } else {
            $aFranchise[$singleJStore->name_tlc]['count'] ++;
        }
    }
}



foreach ($aFranchise as $key => $value) {
    if ($value['count'] < 5) {
        unset($aFranchise[$key]);
    }
}

$filePath = APPLICATION_PATH . '/../public/files/edekaFranchise.csv';

$fh = fopen($filePath, 'w');

fputcsv($fh, array('Franchise', 'Anzahl Standorte', 'Url', 'Telefon'), ';');


foreach ($aFranchise as $key => $value) {
    fputcsv($fh, array($key, $value['count'], trim($value['url']), $value['phone']), ';');
}

fclose($fh);

Zend_Debug::dump($filePath);
