#!/usr/bin/php
<?php
/*
 * Skript zum Auslesen der KaufDa-Prospekt-Click-Out-Infos
 */
chdir(__DIR__);

require_once '../scripts/index.php';

$sPdf = new Marktjagd_Service_Output_Pdf();

$localPdfFilePath = $argv[1];

$aPdfInfos = $sPdf->getAnnotationInfos($localPdfFilePath);

$localJsonFilePath = $argv[2];
$jData = json_decode(file_get_contents($localJsonFilePath));

$aInfos = array();
foreach ($jData->pages as $singleJData) {
    foreach ($singleJData->linkOuts as $singleClickOut) {
        if (is_null($singleClickOut->position)) {
            continue;
        }

        $aInfos[] = array(
            'page' => $singleJData->page,
            'height' => $aPdfInfos[$singleJData->page]->height,
            'width' => $aPdfInfos[$singleJData->page]->width,
            'startX' => $singleClickOut->position->x * $aPdfInfos[$singleJData->page]->width,
            'startY' => $aPdfInfos[$singleJData->page]->height - $singleClickOut->position->y * $aPdfInfos[$singleJData->page]->height,
            'endX' => $singleClickOut->position->x * $aPdfInfos[$singleJData->page]->width + 25,
            'endY' => $aPdfInfos[$singleJData->page]->height - $singleClickOut->position->y * $aPdfInfos[$singleJData->page]->height - 25,
            'link' => $singleClickOut->links->web->href
        );
    }
}

$jsonPath = APPLICATION_PATH . '/../public/files/kaufDa.json';

$fh = fopen($jsonPath, 'w+');
fwrite($fh, json_encode($aInfos));
fclose($fh);

$sPdf->setAnnotations($localPdfFilePath, $jsonPath);

echo "$aPdfInfos\n";