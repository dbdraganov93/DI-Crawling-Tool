#!/usr/bin/php
<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';

$sApi = new Marktjagd_Service_Input_MarktjagdApi();
// API Umstellung Tarife => neue Tarif Logik muss implementiert werden, bevor UNs wieder aufgenommen werden können
$sApi->updateCrawler();