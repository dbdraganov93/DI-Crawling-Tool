#!/usr/bin/php

<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';
/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');

if (count($argv) < 3
    || !$argv[1]) {
    $logger->log($argv[0] . ' [string company ID] [string folder name] [string regex-pattern]', Zend_Log::INFO);
    $logger->log('Liest den Mailordner gemäß Vorgaben aus.', Zend_Log::INFO);
    exit(1);
}

$sMail = new Marktjagd_Service_Transfer_Email($argv[2]);

if (count($argv) == 4) {
    $cMail = $sMail->generateEmailCollection($argv[1], $argv[2], $argv[3])->getElements();
} else {
    $cMail = $sMail->generateEmailCollection($argv[1], $argv[2])->getElements();
}

if (!is_dir(APPLICATION_PATH . '/../public/files/mail/' . $argv[1])) {
    mkdir(APPLICATION_PATH . '/../public/files/mail/' . $argv[1], 0775, true);
}
$fileData = serialize($cMail);
file_put_contents(APPLICATION_PATH . '/../public/files/mail/' . $argv[1] . '/CollectionData.txt', $fileData);
