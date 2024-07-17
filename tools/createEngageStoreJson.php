<?php
chdir(__DIR__);

require_once '../scripts/index.php';

if ($argc != 2) {
    throw new Exception('invalid format: php createEngageJson.php <companyId>');
}

$sApi = new Marktjagd_Service_Input_MarktjagdApi();

$cStores = $sApi->findStoresByCompany($argv[1]);

$count = 1;
$aInfosForJson = [];
foreach ($cStores->getElements() as $eStore) {
    $aInfosForJson[] = [
        'store_number' => $count++,
        'city' => $eStore->getCity(),
        'zipcode' => $eStore->getZipcode(),
        'street' => $eStore->getStreet(),
        'street_number' => $eStore->getStreetNumber(),
        'location' => $eStore->getLatitude() . ',' . $eStore->getLongitude(),
        'store_hours' => $eStore->getStoreHours()
    ];
}

$jInfos = json_encode($aInfosForJson, JSON_UNESCAPED_UNICODE);

$jsonFilePath = APPLICATION_PATH . '/../public/files/tmp/engageStores_' . $argv[1] . '.json';

$fh = fopen($jsonFilePath, 'w+');
fwrite($fh, $jInfos);
fclose($fh);

echo "$jsonFilePath\n";