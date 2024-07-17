<?php

class Task_IndexController extends Zend_Controller_Action
{

    public function showAction()
    {
        $companyForm = new Task_Form_CompanyChangeForm();
        $taskForm = new Task_Form_TaskChangeForm();
        $sTask = new Marktjagd_Database_Service_Task();
        $sAds = new Marktjagd_Database_Service_AdvertisingSettings();
        $sUser = new Marktjagd_Database_Service_User();
        $aAdTasks = array();
        $aAdType = array(
            'brochures' => 0,
            'products' => 1
        );

        $params = $this->_request->getParams();
        $companyForm->populate($params);

        if (array_key_exists('company', $params)) {
            $cTasks = $sTask->findTasksByCompanyId($params['company']);
            $cAds = $sAds->findAdsByCompanyId($params['company']);
            $aAdTasks = array();
            foreach ($cTasks as $singleTask) {
                $aAdTasks[] = $singleTask;
            }

            foreach ($cAds as $singleAd) {
                $aAdTasks[] = $singleAd;
            }

            $taskForm = new Task_Form_TaskChangeForm($params['company']);
        }

        if (array_key_exists('idTask', $params)) {
            $eTask = $sTask->find($params['idTask']);
            $aInfos['taskStart'] = date('d.m.Y', strtotime($eTask->getNextDate()));
            $aInfos['intervallType'] = $eTask->getIntervallType();
            $aInfos['title'] = preg_replace('#.+?\):\s*(.+)#', '$1', $eTask->getTitle());
            $aInfos['description'] = $eTask->getDescription();
            $aInfos['taskType'] = 0;
            
            if (strlen($eTask->getAssignedTo())) {
                $aInfos['assignedTo'] = $sUser->findByIdRedmine($eTask->getAssignedTo())->getIdUser();
            }

            if (!preg_match('#unique#', $eTask->getIntervallType())) {
                $aInfos['intervallLength'] = $eTask->getIntervall();
            }
            
            $taskForm->populate($aInfos);
        }

        if (array_key_exists('idAdvertisingSettings', $params)) {
            $eTask = $sAds->find($params['idAdvertisingSettings']);
            $aInfos['taskStart'] = date('d.m.Y', strtotime($eTask->getNextDate()));
            $aInfos['intervallType'] = $eTask->getIntervallType();
            $aInfos['title'] = $eTask->getTitle();
            $aInfos['description'] = $eTask->getDescription();
            $aInfos['weekDays'] = preg_split('#-#', $eTask->getWeekDays());
            $aInfos['taskType'] = 1;
            $aInfos['taskEnd'] = date('d.m.Y', strtotime($eTask->getEndDate()));
            $aInfos['adType'] = $aAdType[$eTask->getAdType()];
            $aInfos['ticketCheck'] = $eTask->getTicketCheck();

            foreach ($aInfos['weekDays'] as $key => $value) {
                if ($value == '0') {
                    unset($aInfos['weekDays'][$key]);
                }
                else {
                    $aInfos['weekDays'][$key] = $key;
                }
            }

            if (!preg_match('#unique#', $eTask->getIntervallType())) {
                $aInfos['intervallLength'] = $eTask->getIntervall();
            }

            $taskForm->populate($aInfos);
        }

        if (array_key_exists('submit', $params)) {
            if (preg_match('#unique#', $params['intervallType'])) {
                $params['intervallLength'] = '1';
            }

            if ($taskForm->isValid($params)) {
                if ($params['intervallType'] != '6') {
                    $idNewTask = $this->_createDataset($params);
                    if (array_key_exists('idTask', $params)) {
                        $idNewTask['id'] = $params['idTask'];
                        $idNewTask['type'] = '0';
                    }
                    if (array_key_exists('type', $idNewTask)) {
                        exec('php ' . APPLICATION_PATH . '/../scripts/taskCreate.php ' . $params['company'] . ' ' . $idNewTask['id']);
                        $cTasks = $sTask->findTasksByCompanyId($params['company']);
                        $aAdTasks = array();
                        foreach ($cTasks as $singleTask) {
                            $aAdTasks[] = $singleTask;
                        }
                        $sAds = new Marktjagd_Database_Service_AdvertisingSettings();
                        $cAds = $sAds->findAdsByCompanyId($params['company']);
                        foreach ($cAds as $singleAd) {
                            $aAdTasks[] = $singleAd;
                        }
                    }
                }
                exec('php ' . APPLICATION_PATH . '/../scripts/taskCreate.php ' . $params['company'] . ' ' . $idNewTask);
                $taskForm->reset();
                $taskForm->company->setValue($params['company']);
            }
        }

        if (array_key_exists('delete', $params)) {
            $taskForm->company->setValue($params['company']);
        }

        $this->view->cTasks = $aAdTasks;
        $this->view->companyForm = $companyForm;
        $this->view->taskForm = $taskForm;
    }

    /**
     * Funktion, um Datensatz aus Relation zu löschen
     * 
     * @return boolean
     */
    public function deleteAction()
    {
        $params = $this->_request->getParams();
        if (array_key_exists('idTask', $params)) {
            $sTask = new Marktjagd_Database_Service_Task();
            $sTask->deleteTask($params['idTask']);
            $this->_helper->redirector('show', 'index', 'Task', array(
                'company' => $params['company'],
                'delete' => '1'));
        }
        elseif (array_key_exists('idAdvertisingSettings', $params)) {
            $sTask = new Marktjagd_Database_Service_AdvertisingSettings();
            $sTask->deleteAdvertisingSetting($params['idAdvertisingSettings']);
            $this->_helper->redirector('show', 'index', 'Task', array(
                'company' => $params['company'],
                'delete' => '1'));
        }
    }

    /**
     * Funktion, um Datensatz für Relation zu erzeugen
     * 
     * @param array $aValues
     * @return array
     */
    protected function _createDataset($aValues)
    {
        $sUser = new Marktjagd_Database_Service_User();
        $eUser = $sUser->findByIdRedmine($aValues['assignedTo']);
        
        $sTimes = new Marktjagd_Service_Text_Times();
        if ($aValues['taskType'] == '0') {
            $eTask = new Marktjagd_Database_Entity_Task();
            $eTask->setTitle($aValues['title'])
                    ->setTaskStatus('active');
            
            if (is_object($eUser)) {
                $eTask->setAssignedTo($eUser->getIdUserRedmine());
            }

            if (array_key_exists('idTask', $aValues)) {
                $eTask->setIdTask($aValues['idTask']);
            }
        }
        else {
            $eTask = new Marktjagd_Database_Entity_AdvertisingSettings();
            $eTask->setEndDate(date('Y-m-d', strtotime($aValues['taskEnd'])))
                    ->setAdType($aValues['adType'] + 1)
                    ->setTitle($aValues['title'])
                    ->setAdStatus('active')
                    ->setTicketCheck('0');
            
            if ($aValues['ticketCheck'] == '1')
            {
                $eTask->setTicketCheck(1);
            }

            $aWeekDays = array(
                '0',
                '0',
                '0',
                '0',
                '0',
                '0',
                '0',
            );

            foreach ($aWeekDays as $key => $singleDay) {
                if (in_array($key, $aValues['weekDays'])) {
                    $aWeekDays[$key] = '1';
                }
            }
            $eTask->setWeekDays(implode('-', $aWeekDays));

            if (array_key_exists('idAdvertisingSettings', $aValues)) {
                $eTask->setIdAdvertisingSettings($aValues['idAdvertisingSettings']);
            }
        }

        $eTask->setIdCompany($aValues['company'])
                ->setStartDate(date('Y-m-d', strtotime($aValues['taskStart'])))
                ->setDescription($aValues['description'])
                ->setIntervallType($aValues['intervallType'])
                ->setIntervall($aValues['intervallLength'])
                ->setNextDate(date('Y-m-d', strtotime($sTimes->findNextWorkDay($aValues['taskStart'], '0'))));

        return array(
            'type' => $aValues['taskType'],
            'id' => $eTask->save());
    }

}
