<?php
/**
 * Service zum automatisierten Import von auf dem FTP abgelegten Daten
 *
 * Class Marktjagd_Service_Input_FtpTrigger
 *
 */
class Marktjagd_Service_Input_FtpTrigger
{
    /**
     * @var Zend_Log
     */
    protected $_logger;

    /**
     * Konstruktor
     */
    public function __construct()
    {
        $this->_logger = Zend_Registry::get('logger');
    }

    /**
     * Bearbeitet eine ausgelöste FTP-Aktion => generiert ggfs. das Triggerobjekt
     *
     * @param string $user Username
     * @param string $action ausgeführte FTP-Aktion
     * @param string $fileName Pfad zur betroffenen Datei
     * @param string $renamedFrom
     * @return bool|Marktjagd_Entity_Trigger
     */
    public function prepareTrigger($user, $action, $fileName, $renamedFrom)
    {
        $basePath = '/srv/ftp';

        // Fix für UIM
        $prefixPath = '';
        if (strpos($user, '/')) {
            $pos = strpos($user, '/') + 1;
            $prefixPath = substr($user, 0, $pos);
            $user = substr($user, $pos);
        }

        // Prüfen, ob Company oder MJ-Mitarbeiter angemeldet und Pfad korrekt ermitteln
        if (is_numeric($user)) {
            $fileName = $basePath . '/' . $prefixPath . $user . $fileName;
            $renamedFrom = $basePath . '/' . $prefixPath . $user . $renamedFrom;
        } else {
            // Prefix-Path für MJ-Logins ermitteln, aber nicht an Dateinamen setzen
            if (preg_match('#^/(.*?/)[0-9]+/#', $fileName, $matchPrefixPath)) {
                $prefixPath = $matchPrefixPath[1];
            }
            $fileName = $basePath . $fileName;
            $renamedFrom = $basePath . $renamedFrom;
        }

        // Company-Id ermitteln
        $companyId = null;
        $patternCompanyId = '#^/srv/ftp/(' . $prefixPath . ')?(.*?)/#';
        if (preg_match($patternCompanyId, $fileName, $matchCompanyId)) {
            $companyId = $matchCompanyId[2];
        }

        $eTrigger = new Marktjagd_Entity_Trigger();
        $eTrigger->setAction($action)
                 ->setCompanyId($companyId)
                 ->setTriggerType(Marktjagd_Entity_TriggerType::$TYPE_FTP)
                 ->setDestination($fileName)
                 ->setRenamedFrom($renamedFrom)
                 ->setUserName($prefixPath . $user);

        return $eTrigger;
    }
}