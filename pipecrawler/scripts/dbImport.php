#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/index.php';

echo 'checkApi.php started' . "\n";
$date = strtotime('now');
$dayStart = (string) date('Y-m-d', strtotime('-1day'));
$dayEnd = (string) date('Y-m-d');
exec('php checkApi.php 2>&1', $result, $returnVar);
if ($returnVar == 0) {
    echo 'checkApi.php finished successfully.' . "\n";
}
else {
    echo "checkApi.php failed ($returnVar): " . implode("\n", $result);
    exit(-1);
}

$aData = unserialize(end($result));

if (!$aData) {
    echo 'API check failure' . "\n\n";
    exit(10);
}

$sDbStores = new Marktjagd_Database_Service_Company();
$sQualityCheck = new Marktjagd_Service_Output_QualityCheck();
$aParams = array(
    'companyDetailStart' => $dayStart,
    'companyDetailEnd' => $dayEnd,
);

foreach ($aData as $singleDataKey => $singleDataValue) {
    $eQualityCheckErrors = new Marktjagd_Database_Entity_QualityCheckErrors();
    $sDbQuality = new Marktjagd_Database_Service_QualityCheckErrors();
    $sDbCompany = new Marktjagd_Database_Service_Company();
    $sDbQAConfig = new Marktjagd_Database_Service_QualityCheckCompanyInfos();

    $aParams['companyId'] = $singleDataKey;
    $oCompany = $sDbCompany->find($singleDataKey);
    $oConfig = $sDbQAConfig->findByCompanyId($singleDataKey);
    $freshnessStores = $oConfig->getFreshnessStores();
    $freshnessProducts = $oConfig->getFreshnessProducts();
    $freshnessBrochures = $oConfig->getFreshnessBrochures();

    if ($singleDataValue['stores_check'] == '1') {
        $eAmountStores = new Marktjagd_Database_Entity_AmountStores();
        $eAmountStores->setIdCompany($singleDataKey)
                ->setAmountStores($singleDataValue['stores_count'])
                ->setLastTimeChecked((string) date('Y-m-d H:i:s', $date));

        if ($singleDataValue['stores_last_modified']) {
            $eAmountStores->setLastTimeModified((string) date('Y-m-d H:i:s', $singleDataValue['stores_last_modified']));
        }

        if ($singleDataValue['stores_last_import']) {
            $eAmountStores->setLastImport((string) date('Y-m-d H:i:s', $singleDataValue['stores_last_import']));
        }

        $eAmountStores->save();

        $aResult = $sQualityCheck->checkAmountInfos($aParams, 'AmountStores');
        $actualAmount = (float) end($aResult);
        $lastAmount = (float) prev($aResult);
        $freshnessTime = '';

        if (count($aResult) == 4) {
            $lastAmount = $actualAmount;
        }

        if ($freshnessStores) {
            $sQualityCheck->checkForErrorWarnings($singleDataKey, 'stores', $actualAmount, $lastAmount, $singleDataValue['stores_last_modified'], $singleDataValue['stores_last_import'], $date, TRUE);
        }

        $sQualityCheck->checkForErrorWarnings($singleDataKey, 'stores', $actualAmount, $lastAmount, $singleDataValue['stores_last_modified'], $singleDataValue['stores_last_import'], $date);
    }

    if ($singleDataValue['brochures_check'] == '1') {
        if (!count($singleDataValue['brochures_count']) || $singleDataValue['brochures_count'] == 0) {
            $eAmountBrochures = new Marktjagd_Database_Entity_AmountBrochures();
            $eAmountBrochures->setIdCompany($singleDataKey)
                    ->setAmountBrochures('0')
                    ->setLastTimeChecked((string) date('Y-m-d H:i:s', $date));
            $eAmountBrochures->save();
        }
        else {
            foreach ($singleDataValue['brochures_count'] as $singleBrochureStartKey => $singleBrochureStartValue) {
                foreach ($singleBrochureStartValue as $singleBrochureEndKey => $singleBrochureEndValue) {
                    $eAmountBrochures = new Marktjagd_Database_Entity_AmountBrochures();
                    $eAmountBrochures->setIdCompany($singleDataKey)
                            ->setAmountBrochures($singleBrochureEndValue)
                            ->setLastTimeChecked((string) date('Y-m-d H:i:s', $date));

                    if ($singleDataValue['brochures_last_modified']) {
                        $eAmountBrochures->setLastTimeModified((string) date('Y-m-d H:i:s', $singleDataValue['brochures_last_modified']));
                    }

                    if ($singleDataValue['brochures_last_import']) {
                        $eAmountBrochures->setLastImport((string) date('Y-m-d H:i:s', $singleDataValue['brochures_last_import']));
                    }

                    if ($singleBrochureStartKey != 0) {
                        $eAmountBrochures->setStartDate((string) date('Y-m-d H:i:s', $singleBrochureStartKey));
                    }

                    if ($singleBrochureEndKey != 0) {
                        $eAmountBrochures->setEndDate((string) date('Y-m-d H:i:s', $singleBrochureEndKey));
                    }

                    $eAmountBrochures->save();
                }
            }
        }

        $aResult = $sQualityCheck->checkAmountInfos($aParams, 'AmountBrochures');
        $futureAmount = (float) end($aResult);
        $actualAmount = (float) prev($aResult);
        $lastAmount = (float) prev($aResult);

        if (count($aResult) == 4) {
            $lastAmount = $actualAmount;
            $futureAmount = $actualAmount;
        }

        if ($freshnessBrochures) {
            $sQualityCheck->checkForErrorWarnings($singleDataKey, 'brochures', $actualAmount, $lastAmount, $singleDataValue['brochures_last_modified'], $singleDataValue['brochures_last_import'], $date, TRUE);
        }

        $sQualityCheck->checkForErrorWarnings($singleDataKey, 'brochures', $actualAmount, $lastAmount, $singleDataValue['brochures_last_modified'], $singleDataValue['brochures_last_import'], $date, FALSE);
        $sQualityCheck->checkForErrorWarnings($singleDataKey, 'brochures', $futureAmount, $actualAmount, $singleDataValue['brochures_last_modified'], $singleDataValue['brochures_last_import'], $date, FALSE, TRUE);
    }

    if ($singleDataValue['products_check'] == '1') {
        if (!count($singleDataValue['products_count']) || $singleDataValue['products_count'] == 0) {
            $eAmountProducts = new Marktjagd_Database_Entity_AmountProducts();
            $eAmountProducts->setIdCompany($singleDataKey)
                    ->setAmountProducts('0')
                    ->setLastTimeChecked((string) date('Y-m-d H:i:s', $date));
            $eAmountProducts->save();
        }
        else {
            foreach ($singleDataValue['products_count'] as $singleProductStartKey => $singleProductStartValue) {
                foreach ($singleProductStartValue as $singleProductEndKey => $singleProductEndValue) {
                    $eAmountProducts = new Marktjagd_Database_Entity_AmountProducts();
                    $eAmountProducts->setIdCompany($singleDataKey)
                            ->setAmountProducts($singleProductEndValue)
                            ->setLastTimeChecked((string) date('Y-m-d H:i:s', $date));

                    if ($singleDataValue['products_last_modified']) {
                        $eAmountProducts->setLastTimeModified((string) date('Y-m-d H:i:s', $singleDataValue['products_last_modified']));
                    }

                    if ($singleDataValue['products_last_import']) {
                        $eAmountProducts->setLastImport((string) date('Y-m-d H:i:s', $singleDataValue['products_last_import']));
                    }

                    if ($singleProductStartKey != 0) {
                        $eAmountProducts->setStartDate((string) date('Y-m-d H:i:s', $singleProductStartKey));
                    }

                    if ($singleProductEndKey != 0) {
                        $eAmountProducts->setEndDate((string) date('Y-m-d H:i:s', $singleProductEndKey));
                    }

                    $eAmountProducts->save();
                }
            }
        }

        $aResult = $sQualityCheck->checkAmountInfos($aParams, 'AmountProducts');
        $futureAmount = (float) end($aResult);
        $actualAmount = (float) prev($aResult);
        $lastAmount = (float) prev($aResult);

        if (count($aResult) == 4) {
            $lastAmount = $actualAmount;
            $futureAmount = $actualAmount;
        }

        if ($freshnessProducts) {
            $sQualityCheck->checkForErrorWarnings($singleDataKey, 'products', $actualAmount, $lastAmount, $singleDataValue['products_last_modified'], $singleDataValue['products_last_import'], $date, TRUE);
        }

        $sQualityCheck->checkForErrorWarnings($singleDataKey, 'products', $actualAmount, $lastAmount, $singleDataValue['products_last_modified'], $singleDataValue['products_last_import'], $date, FALSE);
        $sQualityCheck->checkForErrorWarnings($singleDataKey, 'products', $futureAmount, $actualAmount, $singleDataValue['products_last_modified'], $singleDataValue['products_last_import'], $date, FALSE, TRUE);
    }
}

echo 'DB Import successful' . "\n\n";
