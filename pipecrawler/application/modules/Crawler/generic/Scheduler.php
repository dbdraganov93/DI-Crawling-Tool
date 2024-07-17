<?php
/**
 * Class Crawler_Generic_Scheduler
 * @author Niko Klausnitzer
 */
class Crawler_Generic_Scheduler {

    /**
    * Liefert ein Array mit allen gültigen Zeit-Werten oder false bei Syntax-Fehlern.
    *
    * @param string $cron
    * @return array oder bool
    */
    protected function _parseCron($cron)
    {
        $cron = explode(' ',trim($cron));
        if(count($cron)!=5) {
            return false;
        }

        $minutes  = $this->_parseCronPart($cron[0],0,59);
        $hours    = $this->_parseCronPart($cron[1],0,23);
        $days     = $this->_parseCronPart($cron[2],1,31);
        $months   = $this->_parseCronPart($cron[3],1,12);
        $weekdays = $this->_parseCronPart($cron[4],0,7);
        if (!$minutes
            || !$hours
            || !$days
            || !$months
            || !$weekdays
        ) {
            return false;
        }
        if(in_array(0,$weekdays)
            && !in_array(7,$weekdays)) {
            $weekdays[] = 7;
        }

        return array($minutes,$hours,$days,$months,$weekdays);
    }

    /**
     * Liefert ein Array mit allen möglichen Werten einer bestimmten Spalte eines Crontabs.
     * @param $part
     * @param $min
     * @param $max
     * @return array|bool
     */
    protected function _parseCronPart($part, $min, $max)
    {
        $values = array();
        $part = trim($part);
        if (preg_match('#^[0-9]+$#', $part)) { // einzelner Integer-Wert (12)
            if ($part < $min
                || $part > $max
            ) {
                return false;
            }
            $values[] = (int) $part;
        } else{
            if ($part == '*') { // alle erlaubten Werte der Spalte (*)
                for ($i = $min; $i <= $max; $i++) {
                    $values[] = $i;
                }
            } else if (preg_match('#\*/([0-9]+)#', $part, $match)) { // nur bestimmte Inkremente (*/5)
                 for ($i = $min; $i <= $max; $i++) {
                     if ($i % $match[1] == 0) {
                         $values[] = $i;
                     }
                 }
            } else if(preg_match('#^[0-9,]+$#', $part)) { // eine Liste von Werten (1,2,3)
                 $parts = explode(',', $part);
                 foreach ($parts as $part) {
                     if ($part >= $min
                         && $part <= $max
                     ) {
                         $values[] = (int)$part;
                     }
                 }
            } else if (preg_match('#^([0-9]+)-([0-9]+)$#', $part, $matches)) { // ein Bereich von Werten (1-5)
                for ($i=max($min,$matches[1]); $i <= min($max,$matches[2]); $i++) {
                    $values[] = (int) $i;
                }
            } else {
                // unbekannte Syntax
                return false;
            }
        }
        return $values;
    }

    /**
     * Merkt Crawler zum Crawlen in der Datenbank vor (Scheduling)
     * @param $cCrawlerConfig
     * @param bool $manual
     */
    public function scheduleEntries($cCrawlerConfig, $manual = false)
    {
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');

        foreach ($cCrawlerConfig as $eCrawlerConfig) {
            // Cronprüfung nur, wenn von Cron gestartet
            if (!$manual) {
                /* @var $eCrawlerConfig Marktjagd_Database_Entity_CrawlerConfig()*/
                $cron = $this->_parseCron($eCrawlerConfig->getExecution());

                if (!$cron) {
                    $logger->log('invalid cron-settings ' . $eCrawlerConfig->getIdCrawlerConfig() . ': ' . $eCrawlerConfig->getExecution(), Zend_Log::ERR);
                    continue;
                }

                // Job überspringen, wenn er laut den Einstellungen jetzt nicht laufen soll:
                $now = explode(' ',date('i H d m N'));
                if (!in_array($now[4],$cron[4])) continue; // Wochentag
                if (!in_array($now[3],$cron[3])) continue; // Monat
                if (!in_array($now[2],$cron[2])) continue; // Tag
                if (!in_array($now[1],$cron[1])) continue; // Stunde
                if (!in_array($now[0],$cron[0])) continue; // Minute
            }

            $crawlerLog = new Marktjagd_Database_Entity_CrawlerLog();
            $crawlerLog->setIdCrawlerConfig($eCrawlerConfig->getIdCrawlerConfig())
                       ->setScheduled(date('Y-m-d H:i:s'));

            /**
             * Setzt die Priorität des Crawlers
             * manuell gestarteter Crawler soll früher drankommen, als ein automatisiert gestarteter Crawler
             */
            if ($manual) {
                $crawlerLog->setPrio(0);
            } else {
                $crawlerLog->setPrio(1);
            }

            /**
             * Check, ob gleicher Crawler schon läuft
             * wenn ja => Logeintrag mit Status "couldn't start" machen
             * wenn nein => Crawler mit waiting eintragen
             */
            $sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();
            if ($sCrawlerLog->isRunning($eCrawlerConfig->getIdCrawlerConfig())) {
                $crawlerLog->setIdCrawlerLogType(Crawler_Generic_Response::COULDNT_START);
            } else {
                $crawlerLog->setIdCrawlerLogType(Crawler_Generic_Response::WAITING);
            }

            $id = $crawlerLog->save();
            $logger->info("scheduled new crawler (idCrawlerConfig:".$crawlerLog->getIdCrawlerConfig().", idCrawlerLog:$id)");
        }
    }
}