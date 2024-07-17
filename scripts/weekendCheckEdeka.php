#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/../scripts/index.php';

$logger = Zend_Registry::get('logger');
$sApi = new Marktjagd_Service_Input_MarktjagdApi();

$groups = [
    'NORD' => [
        'EDEKA + Feinkost' => 73541,
        'Marktkauf' => 73540,
    ],
    'SÜDBAYERN' => [
        'EDEKA' => 72089,
        'Edeka Center' => 72090,
        'E Xpress' => 72301,
        'Marktkauf' => 72091,
    ],
    'SÜDWEST' => [
        'EDEKA' => 71668,
        'E-Center' => 71669,
        'Marktkauf' => 71670,
    ],
    'NORDBAYERN Sachsen Th.' => [
        'Diska' => 69473,
        'EDEKA' => 69470,
        'E center' => 69469,
        'nah und gut' => 69471,
        'Kupsch' => 69474,
        'Marktkauf' => 69472,
        'Naturkind' => 73726,
    ],
    'RHEIN- RUHR' => [
        'EDEKA' => 72178,
        'E-Center' => 72180,
    ],
    'Minden Hannover' => [
        'EDEKA' => 73682,
        'EDEKA Center' => 73684,
        'Marktkauf' => 73683,
    ],
    'Hessenring' => [
        'EDEKA' => 73681,
        'Marktkauf' => 80195,
        'E neukauf' => 80196,
        'E aktiv markt' => 80197,
    ]
];

$errors = [];
foreach ($groups as $group => $companies) {
    foreach ($companies as $name => $companyId) {
        $loggingPrefix = "[$group] $name ($companyId):";
        $logger->info("$loggingPrefix checking ...");

        $logger->info("$loggingPrefix finding all stores...");
        $stores = $sApi->findAllStoresForCompany($companyId);
        $numberOfStores = count($stores);
        $logger->info("$loggingPrefix has $numberOfStores stores");

        $logger->info("$loggingPrefix finding all stores WITH brochures...");
        $storesWithBrochure = $sApi->findStoresWithBrochures($companyId, 500, 'future');
        if (!$storesWithBrochure) {
            $logger->err("$loggingPrefix had an error");
            $errors[] = "$loggingPrefix had an error, probably 0 brochures";
            continue;
        }
        $numberOfStoresWithBrochure = count($storesWithBrochure);
        $logger->info("$loggingPrefix has $numberOfStoresWithBrochure stores WITH brochures");

        $difference = $numberOfStores - $numberOfStoresWithBrochure;
        $logger->info("$loggingPrefix $difference stores have NO brochure!\n");
        if ($difference > 0) {
            $errors[] = "$loggingPrefix $difference stores have NO brochure!";
        }
    }
}

$logger->info("\nSUMMARY\n");
foreach ($errors as $error) {
    $logger->err($error);
}
