#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

$brochureId = $argv[1];

$sHttp = new Marktjagd_Service_Transfer_Http();
$localFolder = $sHttp->generateLocalDownloadFolder('122');
for ($site = 0; $site < 32; $site++) {
    $pdfUrl = 'http://static08.meinprospekt.de/brochures/0000/0000/0005/' . $argv[1] . '/images/large/page_' . $site . '.jpg';
    $sHttp->getRemoteFile($pdfUrl, $localFolder);
}

echo "$localFolder\n";