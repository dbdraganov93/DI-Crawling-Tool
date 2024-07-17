#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');

if (count($argv) < 3
    || $argv[1] == ''
    || $argv[2] == ''
) {
    $logger->log($argv[0] . " [salt] [password]", Zend_Log::INFO);
    $logger->log("Generiert aus Salt und Passwort den entsprechenden FTP-Passwort Hash", Zend_Log::INFO);
    die();
}

$salt = $argv[1];
$password = $argv[2];

echo hash('sha256', $salt . $password) . "\n";