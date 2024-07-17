#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

$baseUrl = 'http://frischecenter-zurheide.de/';
$searchUrl = $baseUrl . 'angebote';

$sPage = new Marktjagd_Service_Input_Page();
$sHttp = new Marktjagd_Service_Transfer_Http();

$localPath = $sHttp->generateLocalDownloadFolder($argv[1]);

$sPage->open($searchUrl);
$page = $sPage->getPage()->getResponseBody();

$pattern = '#<a[^>]*href=\'([^\']+?)\'[^>]*title=\'Angebote\s*Düsseldorf\'#';
if (!preg_match($pattern, $page, $offerLinkMatch)) {
    throw new Exception ('unable to get get offer link.');
}

$sPage->open($offerLinkMatch[1]);
$page = $sPage->getPage()->getResponseBody();

$pattern = '#<img[^>]*src=\'([^\']+?\.jpg)\'#';
if (!preg_match_all($pattern, $page, $brochureImageMatches)) {
    throw new Exception ('unable to get any brochure images.');
}

foreach ($brochureImageMatches[1] as $singleImage) {
    Zend_Debug::dump('http://www.ruhrmedien.de/zhappneu/assets/images/angebote/kw08/duesseldorf/' . $singleImage);
    $sHttp->getRemoteFile('http://www.ruhrmedien.de/zhappneu/assets/images/angebote/kw08/duesseldorf/' . $singleImage, $localPath);
}

Zend_Debug::dump($localPath);