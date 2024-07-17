#!/usr/bin/php
<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';
$logger = Zend_Registry::get('logger');

if (count($argv) < 2) {
    $logger->err('invalid parameter count: php mailsend.php <intervall in hours>');
    die;
}

$startTime = date('Y-m-d H:i:s', strtotime('-' . $argv[1] . 'hours'));
$endTime = date('Y-m-d H:i:s');

$sDbQuality = new Marktjagd_Database_Service_QualityCheckErrors();
$aNewErrors = $sDbQuality->findLatestQualityCheckErrorsAdditions($startTime, $endTime);

$strErrorText = '';
$aErrorTypes = array();
if (count($aNewErrors) != 0) {
    foreach ($aNewErrors as $singleError) {
        if (!array_key_exists($singleError->getType(), $aErrorTypes)) {
            $aErrorTypes[$singleError->getType()] = '';
        }
        if (strlen($aErrorTypes[$singleError->getType()])) {
            $aErrorTypes[$singleError->getType()] .= '<br/>';
        }
        $aErrorTypes[$singleError->getType()] .= '<b>' . $singleError->getCompany()->getIdCompany() . ': ' . $singleError->getCompany()->getName()
                . '</b> -> actual amount: ' . $singleError->getActualAmount()
                . ' - last amount: ' . $singleError->getLastAmount();
    }
}
else {
    $strErrorText = 'no new errors. HOORAY!';
}
if (count($aErrorTypes)) {
    foreach ($aErrorTypes as $errorKey => $errorValue) {
        if (strlen($strErrorText)) {
            $strErrorText .= '<br/><br/>';
        }
        $strErrorText .= '<b>' . strtoupper($errorKey) . ':</b><br/>' . $errorValue;
    }
}

$config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$mail = new Zend_Mail('utf-8');
$mail->setBodyHtml($strErrorText)
        ->setFrom($config->log->mail->from, 'DI Robot')
        ->addTo($config->log->mail->to)
        ->addCC($config->log->mail->cc)
        ->setSubject('QA - Report: ' . $startTime . ' - ' . $endTime)
        ->send();
