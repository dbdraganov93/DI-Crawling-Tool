<?php

/**
 * Class Marktjagd_Service_Input_Trigger
 */
class Marktjagd_Service_Input_Trigger
{
    /**
     * Löst eine Triggeraktion aus
     *
     * @param Marktjagd_Entity_Trigger $eTrigger
     */
    public function trigger($eTrigger)
    {
        // Logging in DB
        $sTriggerType = new Marktjagd_Database_Service_TriggerType();
        $eTriggerType = $sTriggerType->findByName($eTrigger->getTriggerType());

        // Speichern der FTP-Aktion in der TriggerLog Tabelle
        $eTriggerLog = new Marktjagd_Database_Entity_TriggerLog();
        $eTriggerLog->setIdCompany($eTrigger->getCompanyId())
                    ->setFileName($eTrigger->getDestination())
                    ->setAction($eTrigger->getAction())
                    ->setIdTriggerType($eTriggerType->getIdTriggerType())
                    ->setUserName($eTrigger->getUserName());
        
        /**
         * Für Löschen einer Datei nur Logeintrag schreiben,
         * bei Destination mit Punkten im Pfad ebenfalls überspringen
         */
        if ($eTrigger->getAction() == 'DELE'
            || preg_match('#(\.|\.\.)\/#', $eTrigger->getDestination())
        ) {
            return;
        }

        // alle Triggerkonfigurationen für das Unternehmen ermitteln
        $sTriggerConfig  = new Marktjagd_Database_Service_TriggerConfig();
        $cTriggerConfig = $sTriggerConfig->findByTriggerType($eTrigger->getTriggerType());

        /* @var $eTriggerConfig Marktjagd_Database_Entity_TriggerConfig */
        /* @var $cCrawlerConfig Marktjagd_Database_Collection_CrawlerConfig */
        foreach ($cTriggerConfig as $eTriggerConfig) {
            // Prüfen, ob Filename auf das in der DB gespeicherte Pattern matcht
            if (preg_match($eTriggerConfig->getPatternFileName(), $eTrigger->getDestination())) {
                $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
                $cCrawlerConfig = $sCrawlerConfig->findByCompanyTypeStatus(
                    $eTriggerConfig->getIdCompany(),
                    $eTriggerConfig->getCrawlerType()->getType(),
                    'auslösergesteuert');
                
                // Service für Scheduler initialisieren
                $sSchedule = new Crawler_Generic_Scheduler();
                $sSchedule->scheduleEntries($cCrawlerConfig, true);
            }
        }
    }
}