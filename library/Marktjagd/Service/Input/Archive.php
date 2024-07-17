<?php

/**
 * Service zum Einlesen und Verarbeiten von Archiv-Dateien
 *
 * Class Marktjagd_Service_Input_Archive
 */
class Marktjagd_Service_Input_Archive
{
    /**
     * @param string $file Pfad zu der zu entpackenden Datei
     * @param string $target Zielordner, wohin File entpackt werden soll
     * @return bool true, wenn erfolgreich
     */
    function unzip($file, $target) {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');
        umask(0);

        if (!is_dir($target)) {
            @mkdir($target,0775,true);
        }

        $output = null;
        exec("unzip -o $file -d $target", $output, $code);
        if($code) {
            $logger->log('unable to unzip ' . $file . ' to ' . $target . "\n" . implode("\n",$output), Zend_Log::ERR);
            return false;
        }

        // Verzeichnisrechte ändern
        exec('chmod -R 0775 ' . $target);

        return true;
    }
}