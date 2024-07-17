#!/usr/bin/php
<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';
$logger = Zend_Registry::get('logger');

$sQA = new Marktjagd_Service_Output_QualityCheck();
$mail = new Zend_Mail('utf-8');
$config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', 'production');

$aData = $sQA->generateDataIntegrationReport();

$attachment = $mail->createAttachment(file_get_contents($aData['fileName']));
$attachment->filename = 'DI-Report_' . date('Y-m-d') . '.csv';

$mail->setBodyText('DI-Report vom ' . date('Y-m-d') . ' für ' . $aData['amountCompanies'] . ' Unternehmen.')
        ->setFrom($config->log->mail->from, 'DI Robot')
        ->addTo($config->log->mail->to)
        ->setSubject('DI - Report: ' . date('Y-m-d'))
        ->send();