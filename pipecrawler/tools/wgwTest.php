#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

//$sWgwImport = new Wgw_Service_Import_OfferImport(1);
//
//$eArticle = new Marktjagd_Entity_Api_Article();
//
//$eArticle->setTitle('test')
//    ->setText('lorem ipsum')
//    ->setPrice(4.99)
//    ->setSuggestedRetailPrice(5.99)
//    ->setStart('15.03.2019')
//    ->setEnd('27.03.2019');
//
//Zend_Debug::dump($sWgwImport->putOffer($eArticle));
//die;
//
//$sApi = new Marktjagd_Service_Input_MarktjagdApi();
//$cStores = $sApi->findStoresByCompany(1);
//
//$sStoreImport = new Wgw_Service_Import_StoreImport(1);
//$count = 0;
//foreach ($cStores->getElements() as $eStore) {
//    $eStore->setStoreHoursNormalized($eStore->getStoreHours());
//    $eStore->setZipcode('9907');
//    $sStoreImport->putStore($eStore);
//    if ($count++ == 5) {
//        break;
//    }
//}
//
//$sRegions = new Wgw_Service_Export_RegionExport();
//
//foreach (json_decode($sRegions->getRegions())->included as $singleRegion) {
//    $aRegions[$singleRegion->id] = ['name' => strtolower(preg_replace('#[^\w]#', '', $singleRegion->attributes->name))];
//
//}
//
//foreach (json_decode($sRegions->getRegions())->data as $singleRegion) {
//    $aRegions[$singleRegion->id] = ['name' => strtolower(preg_replace('#[^\w]#', '', $singleRegion->attributes->name))];
//
//}
//
//$url = 'http://www.gemeinden.at/gemeinden/plz/';
//$sPage = new Marktjagd_Service_Input_Page();
//
//$aDistrictZipcodes = [];
//for ($i = 1; $i <= 9; $i++) {
//    $sPage->open($url . $i);
//    $page = $sPage->getPage()->getResponseBody();
//
//    $pattern = '#<a[^>]*name="gemeindenliste"[^>]*>(.+?)<\/table#';
//    if (!preg_match($pattern, $page, $zipcodeListMatch)) {
//        throw new Exception('unable to get zipcode list: ' . $i);
//    }
//
/*    $pattern = '#>\s*(\d{4})\s*<.+?>\s*Bezirk\s*([^<]+?)\s*<#';*/
//    if (preg_match_all($pattern, $zipcodeListMatch[1], $zipcodeMatches)) {
//        for ($j = 0; $j < count($zipcodeMatches[1]); $j++) {
//            if (!array_key_exists(strtolower(preg_replace(array('#Sankt#', '#[^\w]#'), array('st', ''), $zipcodeMatches[2][$j])), $aDistrictZipcodes)) {
//                $aDistrictZipcodes[strtolower(preg_replace(array('#Sankt#', '#[^\w]#'), array('st', ''), $zipcodeMatches[2][$j]))] = [];
//            }
//            if (is_null($aDistrictZipcodes[strtolower(preg_replace('#[^\w]#', '', $zipcodeMatches[2][$j]))]) || !in_array($zipcodeMatches[1][$j], $aDistrictZipcodes[strtolower(preg_replace('#[^\w]#', '', $zipcodeMatches[2][$j]))])) {
//                $aDistrictZipcodes[strtolower(preg_replace(array('#Sankt#', '#[^\w]#'), array('st', ''), $zipcodeMatches[2][$j]))][] = $zipcodeMatches[1][$j];
//            }
//        }
//    }
//}
//
//$aOrdered = [];
//foreach ($aDistrictZipcodes as $name => $zipcodes) {
//    foreach ($aRegions as $id => $aInfos) {
//        if (preg_match('#' . $name . '#i', strtolower(preg_replace(array('#Sankt#', '#[^\w]#'), array('st', ''), $aInfos['name'])))
//        || preg_match('#' . strtolower(preg_replace(array('#Sankt#', '#[^\w]#'), array('st', ''), $aInfos['name'])) . '#i', $name)) {
//            foreach ($zipcodes as $singleZipcode) {
//                $aOrdered[$singleZipcode] = $id;
//            }
//        }
//    }
//}
//
//$filePath = APPLICATION_PATH . '/../public/files/tmp/regionsRaw.json';
//$fh = fopen($filePath, 'w+');
//fwrite($fh, json_encode($aOrdered));
//fclose($fh);
//
//Zend_Debug::dump($filePath);die;

//$oResponse = new Crawler_Generic_Response();
//$sDbCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
//
//$strCrawlerConfig = '1504';
//
//$eCrawlerConfig = $sDbCrawlerConfig->findById($strCrawlerConfig);
//
//$oResponse->setLoggingCode(2)
//    ->setFileName('https://s3.eu-west-1.amazonaws.com/content.di-prod.offerista/mjcsv/stores_72840_20190401155233.csv')
//    ->setIsImport(TRUE);
//
//$sWgwImport = new Wgw_Service_Import_Import();
//
//$sWgwImport->import($eCrawlerConfig, $oResponse);

$aCompanyIds = [
    1,
    76,
    83,
    100,
    2,
    238,
    214,
    20,
    280,
    281,
    110,
    199,
    31,
    195,
    182,
    155,
    12,
    17,
    275,
    143,
    7,
    232,
    166
];

$sWgwStoreExport = new Wgw_Service_Export_StoreExport();

foreach ($aCompanyIds as $singleCompanyId) {
    $jStores = $sWgwStoreExport->getAllStores($singleCompanyId);
    $fh = fopen(APPLICATION_PATH . '/../public/files/tmp/backup_' . $singleCompanyId . '.json', 'w+');
    fwrite($fh, $jStores);
    fclose($fh);
}