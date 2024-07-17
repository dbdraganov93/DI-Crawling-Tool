#!/usr/bin/php -d allow_url_fopen=1
<?php
chdir(__DIR__);
require_once __DIR__ . '/index.php';

/* @var $logger Zend_Log */
$logger = Zend_Registry::get('logger');
$logger->info('taskCreate.php gestartet');

$sTask = new Marktjagd_Database_Service_Task();
$sAdSet = new Marktjagd_Database_Service_AdvertisingSettings();
$sRedmineIn = new Marktjagd_Service_Input_Redmine();
$sRedmineOut = new Marktjagd_Service_Output_Redmine();
$sTimes = new Marktjagd_Service_Text_Times();
$sTasks = array();


$aRedmineCompanies = $sRedmineIn->getCompanies('DI - manuelle/wiederkehrende Aufgaben');

if (count($argv) > 1) {
    $fixedVersion = $aRedmineCompanies[(int) $argv[1]]['fixedVersionId'];
    $name = $aRedmineCompanies[(int) $argv[1]]['name'];
    $aRedmineCompanies = array();
    $aRedmineCompanies[$argv[1]]['fixedVersionId'] = $fixedVersion;
    $aRedmineCompanies[$argv[1]]['name'] = $name;
}

foreach ($aRedmineCompanies as $singleCompanyKey => $singleCompanyValue) {

    while (true) {
        $steps = 0;
        if (count($argv) > 2) {
            $aTasks['Task'] = $sTask->findSingleTask($argv[2]);
            $aTasks['AdvertisingSettings'] = $sAdSet->findSingleAd($argv[2]);
        }
        else {
            $aTasks['Task'] = $sTask->findTasksByCompanyId($singleCompanyKey);
            $aTasks['AdvertisingSettings'] = $sAdSet->findAdsByCompanyId($singleCompanyKey);
        }

        if (!count($aTasks['Task']) && !count($aTasks['AdvertisingSettings'])) {
            $logger->info('No tasks or ads found.');
            break;
        }

        foreach ($aTasks as $key => $value) {
            if (count($value)) {
                foreach ($value as $singleTask) {
                    if (!strlen($singleTask->getIdCompany())
                            || (preg_match('#AdvertisingSettings#', $key) && $singleTask->getTicketCheck() != 1)) {
                        continue;
                    }
                    $strStatus = preg_replace('#AdvertisingSettings#', 'Ad', $key) . 'Status';
                    if (preg_match('#^(active)#', $singleTask->{'get' . $strStatus}())) {
                        if (strtotime($singleTask->getNextDate()) < strtotime('now +8 days')) {
                            $sRedmineOut->generateTicketByCompany($singleTask, $singleCompanyValue['fixedVersionId']);
                            $strClass = 'Marktjagd_Database_Entity_' . $key;
                            $newTask = new $strClass();

                            $newTask->setDescription($singleTask->getDescription())
                                    ->setIntervallType($singleTask->getIntervallType())
                                    ->{'setId' . $key}($singleTask->{'getId' . $key}())
                                    ->setStartDate($singleTask->getStartDate())
                                    ->setTitle($singleTask->getTitle())
                                    ->setIdCompany($singleTask->getIdCompany())
                                    ->{'set' . $strStatus}($singleTask->{'get' . $strStatus}())
                                    ->setDateCreation($singleTask->getDateCreation());

                            $weekday = date('w', strtotime($singleTask->getNextDate()));

                            if ($singleTask->getIntervallType() == 'day' && ($weekday == 0 || $weekday == 5 || $weekday == 6)) {
                                for ($i = 0; $i < 2; $i += (int) $singleTask->getIntervall()) {
                                    $sRedmineOut->generateTicketByCompany($singleTask, $singleCompanyValue['fixedVersionId'], $i);
                                }
                                $singleTask->setNextDate(date('Y-m-d', strtotime($singleTask->getNextDate()
                                                        . '+' . $singleTask->getIntervall() . 'days')));
                            }
                            else {
                                $singleTask->setNextDate(date('Y-m-d', strtotime($singleTask->getNextDate()
                                                        . '+' . $singleTask->getIntervall() . $singleTask->getIntervallType())));
                            }



                            if (preg_match('#unique#', $newTask->getIntervallType()) || (preg_match('#AdvertisingSettings#', $key) && strtotime($singleTask->getEndDate()) < strtotime('+8days'))) {
                                $newTask->{'set' . $strStatus}('inactive');
                            }

                            $newTask->setNextDate($singleTask->getNextDate());
                            $newTask->save();
                            $steps++;
                        }

                        $singleTask->setNextDate(date('Y-m-d', strtotime($singleTask->getNextDate()
                                                . '+' . $singleTask->getIntervall() . 'days')));
                    }
                    if (preg_match('#AdvertisingSettings#', $key)
                            && strtotime($singleTask->getEndDate()) < strtotime('+14days')
                            && strtotime($singleTask->getEndDate()) >= strtotime('+8days')
                            && !count($sAdSet->findFutureAdsByCompanyId($singleTask->getIdCompany(), date('Y.m.d H:i:m', strtotime($singleTask->getEndDate() . '+14days'))))) {
                        $singleTask->setTitle('Werbeplan erfragen');
                        $sRedmineOut->generateTicketByCompany($singleTask, $singleCompanyValue['fixedVersionId']);
                    }
                }
                if ($steps == 0) {
                    continue;
                }
            }
        }
        if ($steps == 0) {
            break;
        }
    }
}

$logger->info('taskCreate.php beendet');
