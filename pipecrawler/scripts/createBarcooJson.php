#!/usr/bin/php
<?php

chdir(__DIR__);

require_once '../scripts/index.php';

if (!preg_match('#(api|csv)#i', $argv[1])
    || $argc < 4) {
    echo "invalid arguments supplied!\n";
    echo "php creatBarcooJson.php [api|csv] [companyId] [locationType] [triggerRadius] | [pathToCsv (store_id;store_title;store_city;store_latitude;store_longitude)]\n";
    echo "e.g. php creatBarcooJson.php api 69971 MJSTORES 100\n";
    exit(1);
}

$type = $argv[1];
$companyId = $argv[2];
$locationType = $argv[3];
$triggerRadius = 100;

if (strlen($argv[4]) && is_numeric($argv[4])) {
    $triggerRadius = $argv[4];
}

$aInfoToAdd = array();
if (preg_match('#api#i', $type)) {
    $sApi = new Marktjagd_Service_Input_MarktjagdApi();

    $cStores = $sApi->findStoresByCompany($companyId)->getElements();

    foreach ($cStores as $eStore) {
        $aInfoToAdd['conditions'][] =
            array(
                'text_id' => slugify('geo_POI_' . $locationType . '_cid' . $companyId . '_sid' . $eStore->getId() . '_' . $eStore->getTitle() . '_' . $eStore->getCity()),
                'latitude' => $eStore->getLatitude(),
                'longitude' => $eStore->getLongitude(),
                'trigger_radius' => $triggerRadius
            );
    }
} else {
    if (!is_numeric($argv[4])) {
        $pathToCsv = $argv[4];
    } else {
        $pathToCsv = $argv[5];
    }

    $fh = fopen($pathToCsv, 'r');
    $aHeader = array();
    while (($storeColumn = fgetcsv($fh, 0, ';')) != FALSE) {
        if (!count($aHeader)) {
            $aHeader = $storeColumn;
            continue;
        }

        $storeData = array_combine($aHeader, $storeColumn);

        $aInfoToAdd['conditions'][] =
            array(
                'text_id' => slugify('geo_POI_' . $locationType . '_cid' . $companyId . '_sid' . $storeData['store_id'] . '_' . $storeData['store_title'] . '_' . $storeData['store_city']),
                'latitude' => $storeData['store_latitude'],
                'longitude' => $storeData['store_longitude'],
                'trigger_radius' => $triggerRadius
            );
    }
}

$filePath = APPLICATION_PATH . '/../public/files/ofjson/geo_POI_' . $locationType . '_' . $companyId . '.json';

$fh = fopen($filePath, 'w+');
fwrite($fh, json_encode($aInfoToAdd));
fclose($fh);

echo "$filePath\n";

function slugify($text)
{
    $text = preg_replace('#[^\\pL\d]+#u', '_', $text);

    $text = trim($text, '-');

    if (function_exists('iconv')) {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    }

    $text = preg_replace('#[^-\w]+#', '', $text);
    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}