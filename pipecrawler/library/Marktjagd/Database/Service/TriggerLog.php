<?php

/**
 * Service für DB-Abfragen zur TriggerLog-Tabelle
 *
 * Class Marktjagd_Database_Service_TriggerLog
 */
class Marktjagd_Database_Service_TriggerLog extends Marktjagd_Database_Service_Abstract
{
    /**
     * Ermittelt alle FTP-Aktionen der letzten $hours Stunden
     *
     * @param int $hours Stunden in die Vergangenheit, bis zu denen die FTP-Aktionen aufgelistet werden sollen
     * @return Marktjagd_Database_Collection_TriggerLog
     */
    public function findForLastHours($hours)
    {
        $cTriggerLog = new Marktjagd_Database_Collection_TriggerLog();

        $mTriggerLog = new Marktjagd_Database_Mapper_TriggerLog();
        $mTriggerLog->findForLastHours($hours, $cTriggerLog);

        return $cTriggerLog;
    }
}