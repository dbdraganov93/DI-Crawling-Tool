#!/usr/bin/php
<?php
chdir(__DIR__);


require_once '../scripts/index.php';

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');


$sHttp = new Marktjagd_Service_Transfer_Http();
$localFolder = $sHttp->generateLocalDownloadFolder(888);

$ch = curl_init();
login($ch, $localFolder);
$params = [];
setParams($ch, $params);
$params = array_merge($params, [
    'Accept:application/json, text/javascript, */*; q=0.01',
    'Accept-Encoding:gzip, deflate, br',
    'Accept-Language:de,en-US;q=0.7,en;q=0.3',
    'Connection:keep-alive',
    'DNT:1',
    'Host:onlineforum.franchiseverband.com',
    'Referer:https://onlineforum.franchiseverband.com/members',
    'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:68.0) Gecko/20100101 Firefox/68.0',
    'X-Requested-With:XMLHttpRequest',
]);

$siteContent = getSiteContent($ch, $params);
$json = getJson($siteContent);

$totalPages = ceil($json->pagination->total / $json->pagination->per_page);

$allUrlsToMember = getAllMemberUrls($ch, $params, $totalPages);

Zend_Debug::dump($allUrlsToMember);

$aMemberInfo = getAllMemberInfo($ch, $allUrlsToMember);


Zend_Debug::dump($allUrlsToMember);
die();


function login(&$ch, $localFolder)
{
    curl_setopt($ch, CURLOPT_URL, 'https://onlineforum.franchiseverband.com/users/sign_in');
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:68.0) Gecko/20100101 Firefox/68.0');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "user[email]=nicole.bucher@offerista.com&user[password]=zZWUFf883v9U9Jm");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/cookie-name.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, $localFolder);
    $answer = curl_exec($ch);
    if (curl_error($ch)) {
        echo curl_error($ch);
    }
}

function setParams(&$ch, &$params)
{
    $url = 'https://onlineforum.franchiseverband.com/members';
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $answer = curl_exec($ch);
    if (curl_error($ch)) {
        echo curl_error($ch);
    }

    if (preg_match('#Set-Cookie:\s*(.*?)\%#i', $answer, $match)) {
        $params[] = "Cookie:$match[1]";
    }
    if (preg_match('#name="csrf-token"\s*content="(.*?)"#i', $answer, $match)) {
        $params[] = "X-CSRF-Token:$match[1]";
    }
}

function getSiteContent($ch, $params, $page = 1)
{
    $url = "https://onlineforum.franchiseverband.com/directory/members?page=$page&per_page=15&total_pages=15&total_entries=211&archived=true&hide_grouped_roles=true&_=" . time() . "353";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $params);
    $answer = curl_exec($ch);
    if (curl_error($ch)) {
        echo curl_error($ch);
    }
    return $answer;
}

function getJson($answer)
{
    if (!preg_match('#\s+({.+)#', $answer, $json)) {
        Zend_Debug::dump('no Json extractable');
        die();
    }
    $json = json_decode($json[1]);
    return $json;
}

function getAllMemberUrls(&$ch, $params, $totalPages)
{
    $allURLs = [];
    for ($i = 1; $i <= $totalPages; $i++) {
        sleep(rand(0, 30) / 10);
        $siteContent = getSiteContent($ch, $params, $i);
        $json = getJson($siteContent);
        foreach ($json->entries as $entry) {
            $allURLs[] = "https://onlineforum.franchiseverband.com$entry->path";
        }
    }
    return $allURLs;
}

function getAllMemberInfo(&$ch, $allUrlsToMember)
{
    $params = [
        'Host: onlineforum.franchiseverband.com',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:68.0) Gecko/20100101 Firefox/68.0',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: de,en-US;q=0.7,en;q=0.3',
        'Accept-Encoding: gzip, deflate, br',
        'DNT: 1',
        'Connection: keep-alive',
        'Cookie: remember_user_token=BAhbCFsGSSIdNWQyZjEyZWE4MzI3M2YwNGNkZjRkOTM1BjoGRVRJIiIkMmEkMTAkbzFLcWRtL3dCNms2MVVMUnFGR3dndQY7AFRJIhcxNTYzMzcwNDY2Ljk1Nzc4NDQGOwBG--0fbaf7b9050d69709b5c7bad3ef1fa674f714166; tixxt_session_production=VjhEbnlIaG5oTFRMMjF6NlF4VVQ3MUpXMEYxVCtxc2Vsa2gvaXVyQWJkenJWTFNjZUdtNmVSZzV5R0hTSEcwRGkvWk9jZWdhNTJMem1YL3NxMVlOUzFwTnB6V0xFYXVaSDV2N1djdk1pTFROdkZub3FoK3NtMWpsdXpyL28vdmdyZyt1SzdrM01aV0tjN3ZUMXlMOHpoMm8xaSsvZmhxRWRyTUVMNTJ2Ly9GZExSbWFjdUhxMkJDWld0c0J3T0ovYUszNXFuTWg1YjZ0eGRtUVhaeWV6MHptVjh4THdlUDdicm5VTmZvOFFGc1ExWnp2Tk5SVzZFSXVaa3oxUkNYWHMzN2x1d0VQazdvT1dmeWdQVU9FRlNETFFtRUQvQ2FNQTdWM2dvZEpGaGw0T041RkdHWHpFcGJVNXV2TEpqcG8vRlFMVHI3RmpOUzh5RnpFaE4yQmIwNndESDE0SGlIZ08ycVZtWkpicTRVQ2R1Vnd3SzA1QlBKS1V5NFRSQ2FtdENJNFlpaVkrUDBEOVBIUW5PMDVJNnZzOVlqL3RxVzlpb3FueXRGeFAybz0tLVBiTk14NVBDZXJ2bWg3SFo5VlRDbWc9PQ%3D%3D--6536ea3213f3290b4eda4fb66cb541fcaa1d905a',
        'Upgrade-Insecure-Requests: 1',
        'If-None-Match: W/"f929e9e7f70540d9477ff18f1c2153bc"',
        'Cache-Control: max-age=0',
    ];
    foreach ($allUrlsToMember as $url) {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $params);
        curl_setopt($ch, CURLOPT_ENCODING, 'identity');
        $answer = curl_exec($ch);
        Zend_Debug::dump($answer);
        $answer = preg_split('#Content-Encoding:\s*gzip#', $answer)[1];
        Zend_Debug::dump($answer);
        $answer = gzencode($answer);
        Zend_Debug::dump($answer);
        $answer = gzdecode($answer);
        Zend_Debug::dump($answer);
        die();

    }
}