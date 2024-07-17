#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');

$aUrls = array(
    'http://www.kaufda.de/Berlin/Haendlerverzeichnis',
    'http://www.kaufda.de/Hamburg/Haendlerverzeichnis',
    'http://www.kaufda.de/Muenchen/Haendlerverzeichnis',
    'http://www.kaufda.de/Koeln/Haendlerverzeichnis',
    'http://www.kaufda.de/Frankfurt-am-Main/Haendlerverzeichnis',
    'http://www.kaufda.de/Stuttgart/Haendlerverzeichnis',
    'http://www.kaufda.de/Duesseldorf/Haendlerverzeichnis',
    'http://www.kaufda.de/Dortmund/Haendlerverzeichnis',
    'http://www.kaufda.de/Essen/Haendlerverzeichnis',
    'http://www.kaufda.de/Bremen/Haendlerverzeichnis',
    'http://www.kaufda.de/Leipzig/Haendlerverzeichnis',
    'http://www.kaufda.de/Dresden/Haendlerverzeichnis',
    'http://www.kaufda.de/Hannover/Haendlerverzeichnis',
    'http://www.kaufda.de/Nuernberg/Haendlerverzeichnis',
    'http://www.kaufda.de/Duisburg/Haendlerverzeichnis',
    'http://www.kaufda.de/Bochum/Haendlerverzeichnis'
);

$sPage = new Marktjagd_Service_Input_Page();
$aCompany = array();
foreach ($aUrls as $url) {
    $sPage->open($url);
    $page = $sPage->getPage()->getResponseBody();

    if (preg_match('#<div[^>]*class="[^"]*alphabeticList[^"]*">(.*?)</div>#is', $page, $matchContent)) {
        if (preg_match_all('#<li>\s*<a[^>]*>(.*?)</a>#', $matchContent[1], $matchCompany)) {
            foreach ($matchCompany[1] as $matchCompanyString) {
                $aCompany[md5($matchCompanyString)] = $matchCompanyString;
            }
        }
    }
}

natcasesort($aCompany);

foreach ($aCompany as $company) {
    echo $company . "\n";
}