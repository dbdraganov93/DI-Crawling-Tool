#!/usr/bin/php
<?php
chdir(__DIR__);

require_once __DIR__ . '/index.php';
/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
$logger->info('ftp.php gestartet');
try {
    $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $conn = mysqli_connect(
            $configIni->resources->multidb->db2->host,
            $configIni->resources->multidb->db2->username,
            $configIni->resources->multidb->db2->password,
            $configIni->resources->multidb->db2->dbname
    );

    $result = mysqli_query($conn, "SELECT * FROM transfers WHERE handeled = 0");

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

        foreach ($aResult as $singleResult) {
            if (preg_match('#\/srv\/data\/ftp\/([^\/]+?)\/#', $singleResult['file'], $idCompanyMatch)) {
                $sTrigger = new Marktjagd_Service_Input_Trigger();
                $eTrigger = new Marktjagd_Entity_Trigger();

                $eTrigger->setAction($singleResult['command'])
                        ->setUserName($singleResult['user'])
                        ->setDestination($singleResult['file'])
                        ->setCompanyId($idCompanyMatch[1])
                        ->setTriggerType(Marktjagd_Entity_TriggerType::$TYPE_FTP);

                $sTrigger->trigger($eTrigger);

                $time = $singleResult['time'];
                $filePath = $singleResult['file'];
                $user = $singleResult['user'];
                $command = $singleResult['command'];

                $update = mysqli_query($conn, "UPDATE transfers SET handeled = 1 WHERE time = '$time'"
                        . " AND file = '" . mysqli_real_escape_string($conn, $filePath) . "' AND user = '$user' AND command = '$command'");

                if (!$update) {
                    throw new Exception('error while updating transfers table: ' . mysqli_error($conn));
                }
            }
        }
    }
    mysqli_close($conn);
    $logger->info('ftp.php beendet.');
    $logger->__destruct();
} catch (Exception $e) {
    $logger->log('Fehler im FTP-Script aufgetreten:' . "\n"
            . $e->getMessage() . "\n"
            . print_r($e->getTrace(), true), Zend_Log::CRIT);
    $logger->__destruct();
}
