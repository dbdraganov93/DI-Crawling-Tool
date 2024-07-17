#!/usr/bin/php
<?php
chdir(__DIR__);

require_once '../scripts/index.php';

$logger = Zend_Registry::get('logger');

$sEmail = new Marktjagd_Service_Transfer_Email();

if ($argc < 4) {
    $logger->log("invalid parameter amount: php sendEmail.php [sender] [receiver] [ftp path for attachments] [pattern]", Zend_Log::INFO);
    die;
}

$aInfos = [
    'text' => 'see attachments',
    'subject' => 'email with attachments',
    'from' => $argv[1],
    'to' => $argv[2]
];

$sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
$localPath = $sFtp->connect($argv[3], TRUE);
foreach ($sFtp->listFiles() as $singleFile) {
    if (preg_match('#^(\.)#', $singleFile)) {
        continue;
    }
    if ($argc == 5) {
        if (preg_match('#' . $argv[4] . '#', $singleFile)) {
            $aInfos['attachment'][] = $sFtp->downloadFtpToDir($singleFile, $localPath);
        }
    } else {
        $aInfos['attachment'][] = $sFtp->downloadFtpToDir($singleFile, $localPath);
    }
}
$sFtp->close();

$sEmail->sendMail($aInfos);