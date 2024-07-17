<?php

/**
 * Class Marktjagd_Service_Output_Redmine
 */
class Marktjagd_Service_Output_Redmine
{

    const ARTICLES_KATEGORIE = '479';
    const BROCHURES_KATEGORIE = '478';
    const STORES_KATEGORIE = '480';
    const AUTOMATICALLY_GENERATED_KATEGORIE = '481';

    /**
     *
     * @param Marktjagd_Database_Entity_Task $oTask
     * @param int $weekend
     *
     * @return bool
     */
    public function generateTicketByCompany($oTask, $fixedVersionId, $weekend = 0)
    {
        $sTimes = new Marktjagd_Service_Text_Times();
        $sDbCompany = new Marktjagd_Database_Service_Company();
        $companyId = $oTask->getIdCompany();
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', 'production');
        $config = $configIni->redmine->mj->apikey;

        $assignmentId = '';
        if (is_a($oTask, 'Marktjagd_Database_Entity_AdvertisingSettings') && strlen($oTask->getAssignedTo())) {
            $assignmentId = $oTask->getAssignedTo();
        }

        $client = new Redmine\Client('https://redmine.offerista.com', $config);
        $client->api('issue')->create(array(
            'project_id' => 102,
            'subject' => $sDbCompany->find($companyId)->getName() . ': ' . $oTask->getTitle(),
            'description' => $oTask->getDescription(),
            'fixed_version_id' => $fixedVersionId,
            'tracker_name' => 'Aufgabe',
            'status_name' => 'Neu',
            'priority_name' => 'Normal',
            'start_date' => $sTimes->findNextWorkDay($oTask->getNextDate() . '-1 days', 0),
            'due_date' => date('Y-m-d', strtotime($oTask->getNextDate() . '+' . $weekend . 'days')),
            'assigned_to_id' => $assignmentId
        ));
        return true;
    }

    /**
     *
     * @param Marktjagd_Database_Entity_CrawlerLog $oError
     */
    public function generateErrorTicket($oError)
    {
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', 'production');
        $config = $configIni->redmine->mj->apikey;

        $effort = 2;
        if (preg_match('#(article|brochure)#', $oError->getCrawlerConfig()->getCrawlerType()->getType())) {
            $effort = 3;
        }


        $client = new Redmine\Client('https://redmine.offerista.com', $config);
        $client->api('issue')->create(array(
            'project_id' => 10,
            'subject' => preg_replace('#\&#', '&amp;', $oError->getCrawlerConfig()->getCompany()->getName())
                . ' (ID: ' . $oError->getCrawlerConfig()->getCompany()->getIdCompany() . ') '
                . ucfirst($oError->getCrawlerConfig()->getCrawlerType()->getType()) . '-Crawler fehlerhaft',
            'description' => 'Fehlermeldung:<pre>' . $oError->getErrorMessage() . '</pre>',
            'tracker_id' => '46',
            'priority_id' => '4',
            'estimated_hours' => $effort,
            'custom_fields' => [
                [
                    'id' => 34,
                    'value' => 1,
                ]
            ],
            'category_id' => $this->getCategoryID($oError),
        ));
    }

    /**
     * @param $oError
     * @return string
     */
    private function getCategoryID($oError)
    {
        if (preg_match('#brochure#i', $oError->getCrawlerConfig()->getCrawlerType()->getType())) {
            return self::BROCHURES_KATEGORIE;
        } else if (preg_match('#store#i', $oError->getCrawlerConfig()->getCrawlerType()->getType())) {
            return self::STORES_KATEGORIE;
        } else if (preg_match('#article#i', $oError->getCrawlerConfig()->getCrawlerType()->getType())) {
            return self::ARTICLES_KATEGORIE;
        } else {
            return self::AUTOMATICALLY_GENERATED_KATEGORIE;
        }
    }

    public function updateTicket($ticketId, $aInfosToUpdate)
    {
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', 'production');
        $config = $configIni->redmine->mj->apikey;

        $client = new Redmine\Client('https://redmine.offerista.com', $config);
        $client->api('issue')->update($ticketId, $aInfosToUpdate);

        return true;
    }

    protected function _findCompanies()
    {
        $sRedmine = new Marktjagd_Service_Input_Redmine();
        $aCompany = $sRedmine->getCompanies('DI - manuelle/wiederkehrende Aufgaben');

        foreach ($aCompany as $key => $value) {
            $aReturn[$key] = $value;
        }

        return $aReturn;
    }

}
