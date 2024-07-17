#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/index.php';

$sGoogleSpreadSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
$sGoogleSpreadsheet = new Marktjagd_Service_Output_GoogleSpreadsheetWrite();
$sApi = new Marktjagd_Service_Input_MarktjagdApi();

$aInfos = $sGoogleSpreadSheet->getFormattedInfos('1EkMdaxOJUce5IR9G2oRzB0l0H45jzFNV1WvFIsVk3UA', 'A', 'F');

for ($i = 0; $i < count($aInfos); $i++) {
    $aBrochures = $sApi->findActiveBrochuresByCompany($aInfos[$i]['Company ID']);
    foreach ($aBrochures as $brochureId => $singleBrochure) {
        if (preg_match('#' . $aInfos[$i]['Brochure Number'] . '#', $singleBrochure['brochureNumber'])
            && time(date('d.m.Y', strtotime($aInfos[$i]['Valid from (dd.mm.yyyy)']))) == time(date('d.m.Y', strtotime($singleBrochure['validFrom'])))) {
            $aBrochureInfos = [
                [
                    $aInfos[$i]['Company ID'],
                    $aInfos[$i]['Retailer Name'],
                    $aInfos[$i]['Valid from (dd.mm.yyyy)'],
                    $brochureId,
                    $aInfos[$i]['Link to PDF brochure'],
                    $aInfos[$i]['Brochure Number']
                ]];

            $sGoogleSpreadsheet->writeGoogleSpreadsheet($aBrochureInfos, '1EkMdaxOJUce5IR9G2oRzB0l0H45jzFNV1WvFIsVk3UA', FALSE, 'A' . ($i + 2));
        }
    }
}