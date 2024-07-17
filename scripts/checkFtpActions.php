#!/usr/bin/php
<?php
chdir(__DIR__);
require_once __DIR__ . '/index.php';

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
$logger->info('checkFtpActions.php gestartet');
try {
    $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $sCompany = new Marktjagd_Database_Service_Company();
    $hours = 16;

    if (date('H') == '15') {
        $hours = 8;
    }

    $conn = mysqli_connect(
            $configIni->resources->multidb->db2->host, $configIni->resources->multidb->db2->username, $configIni->resources->multidb->db2->password, $configIni->resources->multidb->db2->dbname
    );

    $result = mysqli_query($conn, "SELECT * FROM transfers WHERE TIMESTAMPDIFF(HOUR, time, NOW()) <= $hours");

    if (mysqli_num_rows($result) > 0) {
        // output data of each row
        while ($row = mysqli_fetch_assoc($result)) {
            $aResult[] = array(
                'time' => $row['time'],
                'user' => $row['user'],
                'command' => $row['command'],
                'file' => $row['file'],
                'old_name' => $row['old_name'],
                'handeled' => $row['handeled']
            );
        }
    }
    mysqli_close($conn);

    if (count($aResult)) {
        // Mail initialisieren
        $configMail = array(
            'auth' => 'login',
            'username' => $configIni->mail->smtp->user,
            'password' => $configIni->mail->smtp->pass,
            'ssl' => 'tls',
            'port' => $configIni->mail->smtp->port
        );
        $smtp = new Zend_Mail_Transport_Smtp($configIni->mail->smtp->host, $configMail);

        $mail = new Zend_Mail('utf-8');
        $mail->setFrom($configIni->log->mail->from)
                ->addTo($configIni->log->mail->to)
                ->addCc($configIni->log->mail->cc)
                ->setSubject('FTP-Upload-Report')
                ->setDefaultTransport($smtp);

        $text = 'Company-Id;Company-Name;Nutzername/Login;Dateiname;Aktion;Zeit' . "\n";

        /* @var $eTriggerLog Marktjagd_Database_Entity_TriggerLog */
        foreach ($aResult as $singleResult) {
            // nur Hinzufügen, wenn Unternehmenslogin (diese sind immer numerisch)
            if (preg_match('#[0-9]#', $singleResult['user']) && preg_match('#\/srv\/ftp\/([^\/]+?)\/#', $singleResult['file'], $idCompanyMatch)) {
                $text .= $idCompanyMatch[1] . ';'
                        . $sCompany->find($idCompanyMatch[1])->getName() . ';'
                        . $singleResult['user'] . ';'
                        . $singleResult['file'] . ';'
                        . $singleResult['command'] . ';'
                        . $singleResult['time'] . "\n";
            }
        }

        $mail->setBodyText('Anbei alle FTP-Uploads die seit dem letzten Report getätigt wurden.');

        $at = $mail->createAttachment($text);
        $at->type = 'text/csv';
        $at->filename = 'ftp-report-' . date('Y-m-d-H-i-s') . '.csv';

        $mail->send();
        $logger->info('checkFtpActions.php Mail versendet');
    }

    $logger->info('checkFtpActions.php beendet');
    $logger->__destruct();
} catch (Exception $e) {
    $logger->log('Fehler im checkFtpActions-Script aufgetreten:' . "\n"
            . $e->getMessage() . "\n"
            . print_r($e->getTrace(), true), Zend_Log::CRIT);
    $logger->__destruct();
}