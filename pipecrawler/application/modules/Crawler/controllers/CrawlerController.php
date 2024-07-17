<?php

/**
 * Class Crawler_CrawlerController
 */
class Crawler_CrawlerController extends Zend_Controller_Action
{

    public function indexAction()
    {
        $this->redirect('/Crawler/crawler/store');
    }

    /**
     * Zeigt alle Artikel-Crawler in der Übersicht an
     */
    public function articleAction()
    {
        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $cCrawlerConfig = $sCrawlerConfig->findByType('articles');

        $this->view->cCrawlerConfig = $cCrawlerConfig;
    }

    /**
     * Zeigt alle PDF-Crawler in der Übersicht an
     */
    public function brochureAction()
    {
        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $cCrawlerConfig = $sCrawlerConfig->findByType('brochures');

        $this->view->cCrawlerConfig = $cCrawlerConfig;
    }

    /**
     * Zeigt alle Storecrawler in der Übersicht an
     */
    public function storeAction()
    {
        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $cCrawlerConfig = $sCrawlerConfig->findByType('stores');

        $this->view->cCrawlerConfig = $cCrawlerConfig;
    }

    /**
     * Öffnet die Crawlerdetail-Seite mit Bearbeitungsmöglichkeit und Loganzeige
     */
    public function crawlerdetailAction()
    {
        $idCrawlerConfig = $this->_request->getParam('idCrawlerConfig');
        $user = Zend_Auth::getInstance()->getStorage()->read();
        $userLevel = $user->role['level'];

        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $eCrawlerConfig = $sCrawlerConfig->findById($idCrawlerConfig);

        $form = new Crawler_Form_CrawlerEditForm($eCrawlerConfig, $userLevel);

        if ($userLevel <= 50) {
            $startCrawlerForm = new Crawler_Form_CrawlerStartForm($idCrawlerConfig);
        } else {
            $startCrawlerForm = '';
        }

        $sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();
        $cCrawlerLog = $sCrawlerLog->findLastProcessesByCrawler($idCrawlerConfig, 10);

        $sTriggerConfig = new Marktjagd_Database_Service_TriggerConfig();

        $this->view->eCrawlerConfig = $eCrawlerConfig;
        $this->view->cCrawlerLog = $cCrawlerLog;

        if ($this->getRequest()->isPost()) {
            if ($form->isValid($this->getRequest()->getPost())) {
                // Form valide und Submitbutton wurde gedrückt
                $params = $this->_request->getParams();

                $calculatedRuntime = $sCrawlerLog->calculateEstimatedRuntime($idCrawlerConfig);

                $eCrawlerConfig->setIdAuthor($params['author'])
                    ->setDescription($params['description'])
                    ->setExecution($params['execution'])
                    ->setLastModified((string)date('Y-m-d H:i:s'))
                    ->setFileName($params['pathToFile'])
                    ->setTicketCreate(false)
                    ->setSystemRunning($params['system']);

                $eCrawlerConfig->setIdCrawlerBehaviour($params['behaviour']);

                if ($calculatedRuntime) {
                    $eCrawlerConfig->setRuntime($calculatedRuntime);
                }

                if (!preg_match('#' . $params['status'] . '#', $eCrawlerConfig->getCrawlerStatus())) {
                    $eCrawlerConfig->setCrawlerStatus($params['status'])
                        ->setStatusChanged((string)date('Y-m-d H:i:s'));
                }

                if (array_key_exists('errorMessage', $params)) {
                    if (array_key_exists('0', $params['errorMessage'])) {
                        $eCrawlerConfig->setTicketCreate(true);
                    }
                }

                $eCrawlerConfig->save();

                // Löschen evtl. vorhandener Trigger
                if ((!preg_match('#auslösergesteuert#', $params['status']) && array_key_exists('idTriggerConfig', $params)) || $params['triggerType'] == 'null'
                ) {
                    if (array_key_exists('idTriggerConfig', $params)) {
                        $sTriggerConfig->deleteByTriggerConfigId($params['idTriggerConfig']);
                    }
                }

                // Hinzufügen von Triggern
                if (preg_match('#auslösergesteuert#', $params['status']) && $params['triggerType'] != 'null'
                ) {
                    $eTriggerConfig = new Marktjagd_Database_Entity_TriggerConfig();
                    if (array_key_exists('idTriggerConfig', $params) && $params['idTriggerConfig'] != ''
                    ) {
                        $eTriggerConfig->setIdTriggerConfig($params['idTriggerConfig']);
                    }

                    $eTriggerConfig->setIdCompany($eCrawlerConfig->getIdCompany())
                        ->setIdCrawlerType($eCrawlerConfig->getIdCrawlerType())
                        ->setIdTriggerType($params['triggerType'])
                        ->setPatternFileName($params['triggerPattern'])
                        ->setIdCrawlerConfig($params['idCrawlerConfig']);
                    $eTriggerConfig->save();
                }

                $eCrawlerConfig = $sCrawlerConfig->findById($eCrawlerConfig->getIdCrawlerConfig());

                // Formularelemente neu laden
                $form = new Crawler_Form_CrawlerEditForm($eCrawlerConfig, $userLevel);
            }
        }


        $this->view->form = $form;
        $this->view->crawlerStatus = $eCrawlerConfig->getCrawlerStatus();
        $this->view->formStart = $startCrawlerForm;
    }

    /**
     * Fügt einen neuen Crawler hinzu
     */
    public function crawleraddAction()
    {
        $params = $this->_request->getParams();

        $this->view->formTitle = 'Content-Crawler';
        $form = new Crawler_Form_CrawlerAddForm();

        if ($this->getRequest()->isPost()) {
            if ($form->isValid($params)) {
                // Form valide und Submitbutton wurde gedrückt
                $eCrawlerConfig = new Marktjagd_Database_Entity_CrawlerConfig();
                $eCrawlerConfig->setIdCompany($params['companyId'])
                    ->setIdCrawlerType($params['crawlerType'])
                    ->setFileName($params['pathToFile'])
                    ->setIdCrawlerBehaviour($params['behaviour'])
                    ->setCrawlerStatus($params['status'])
                    ->setIdAuthor($params['author'])
                    ->setDescription($params['description'])
                    ->setExecution($params['execution'])
                    ->setLastModified((string)date('Y-m-d H:i:s'))
                    ->setTicketCreate(false)
                    ->setSystemRunning($params['system']);

                $eCrawlerConfig->setIdCrawlerBehaviour($params['behaviour']);

                if (array_key_exists('errorMessage', $params)) {
                    if (array_key_exists('0', $params['errorMessage'])) {
                        $eCrawlerConfig->setTicketCreate(true);
                    }
                }

                $idCrawlerConfig = $eCrawlerConfig->save();

                // Hinzufügen von Triggern
                if (preg_match('#auslösergesteuert#', $params['status']) && $params['triggerType'] != 'null'
                ) {
                    $eTriggerConfig = new Marktjagd_Database_Entity_TriggerConfig();
                    $eTriggerConfig->setIdCompany($eCrawlerConfig->getIdCompany())
                        ->setIdCrawlerType($eCrawlerConfig->getIdCrawlerType())
                        ->setIdTriggerType($params['triggerType'])
                        ->setPatternFileName($params['triggerPattern'])
                        ->setIdCrawlerConfig($idCrawlerConfig);
                    $eTriggerConfig->save();
                }

                switch ($params['crawlerType']) {
                    case '1':
                        $this->redirect('/Crawler/crawler/article');
                        break;
                    case '2':
                        $this->redirect('/Crawler/crawler/brochure');
                        break;
                    case '3':
                        $this->redirect('/Crawler/crawler/store');
                        break;
                    default:
                        $this->redirect('');
                }
            }
        }

        $this->view->form = $form;
    }

    /**
     * Startet einen Crawler und kehrt zur Konfigurationsseite zurück
     */
    public function crawlerstartAction()
    {
        $idCrawlerConfig = $this->_request->getParam('idCrawlerConfig');

        $cCrawlerConfig = new Marktjagd_Database_Collection_CrawlerConfig();
        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $cCrawlerConfig[] = $sCrawlerConfig->findById($idCrawlerConfig);
        $sScheduler = new Crawler_Generic_Scheduler();
        $sScheduler->scheduleEntries($cCrawlerConfig, true);
        $this->redirect('/Crawler/crawler/crawlerdetail/idCrawlerConfig/' . $idCrawlerConfig);
    }

    public function retailerAction()
    {
        $retailerInfoForm = new Crawler_Form_RetailerInfoForm();
        $sDbRetailer = new Marktjagd_Database_Service_AdditionalRetailerInfos();
        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $configIni = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', 'production');

        $params = $this->_request->getParams();
        $aInfos = $sDbRetailer->findAll();

        if ($this->_request->isPost()) {
            $params = $this->_request->getParams();
            if ($retailerInfoForm->isValid($params)) {
                $aInfosToChange = array();
                if (preg_match('#update#', $params['actionSelect'])) {
                    $amountSelectedFields = 0;
                    foreach ($params as $singleSelectKey => $singleSelectValue) {
                        if (preg_match('#fieldSelect#', $singleSelectKey)) {
                            $amountSelectedFields++;
                        }
                    }
                    for ($i = 0; $i < $amountSelectedFields; $i++) {
                        $strFieldSelect = 'fieldSelect_' . (string)$i;
                        $strFieldText = 'fieldText_' . (string)$i;
                        $ignoreValue = 'ignoreValue_' . (string)$i;
                        $pattern = '#nope#';
                        if (!preg_match($pattern, $params[$strFieldSelect])) {
                            if ((int)$params[$ignoreValue] == 1) {
                                $aInfosToChange[$params[$strFieldSelect]] = '[[NULL]]';
                            } elseif (strlen($params[$strFieldText])) {
                                $aInfosToChange[$params[$strFieldSelect]] = $params[$strFieldText];
                            }
                        }
                    }
                }

                $eRetailerInfos = new Marktjagd_Database_Entity_AdditionalRetailerInfos();

                $aTimes = array(
                    '#(tage?)$#i' => 'days',
                    '#(wochen?)$#i' => 'weeks',
                    '#(monate?)$#i' => 'months',
                    '#(jahre?)$#i' => 'years'
                );

                $pattern = '#' . preg_replace(array('#\[\[COMPANYID\]\]#', '#\[\[STOREID\]\]#'), array('(\d+?)', '(\d+)'), $configIni->retailer->urlpattern) . '#';

                if (!preg_match($pattern, $params['storeUrl'], $idMatch)) {
                    throw new Exception('unable to get store and / or company id: ' . $params['storeUrl']);
                }

                $eRetailerInfos->setAction($params['actionSelect'])
                    ->setUser($userInfo->userName)
                    ->setIdCompany($idMatch[1])
                    ->setIdStore($idMatch[2])
                    ->setTimeAdded(date('Y-m-d H:i:s'));

                foreach ($aTimes as $timeKey => $timeValue) {
                    if (preg_match($timeKey, $params['validityLength'])) {
                        $eRetailerInfos->setValidityLength(preg_replace($timeKey, $timeValue, $params['validityLength']));
                        break;
                    }
                }

                if (count($aInfosToChange)) {
                    $strInfosToChange = json_encode($aInfosToChange);
                    $eRetailerInfos->setInfosToChange($strInfosToChange);
                }

                if ((preg_match('#update#', $params['actionSelect']) && strlen($eRetailerInfos->getInfosToChange())) || preg_match('#ignore#', $params['actionSelect'])) {
                    $forceInsert = TRUE;
                    if ($sDbRetailer->find($eRetailerInfos->getIdStore())->getIdStore()) {
                        $forceInsert = FALSE;
                    }
                    $eRetailerInfos->save(TRUE, $forceInsert);
                }
                $this->redirect('/Crawler/crawler/retailer');
            }
        }
        if (array_key_exists('store', $params) && array_key_exists('delete', $params)) {
            $sDbRetailer->deleteByStoreId($params['store']);

            $this->redirect('/Crawler/crawler/retailer');
        }

        if (array_key_exists('store', $params) && !array_key_exists('delete', $params)) {
            $storeInfos = $sDbRetailer->find($params['store']);
            $aInfosToChange = NULL;
            if (!is_null($storeInfos->getInfosToChange())) {
                $aInfosToChange = json_decode($storeInfos->getInfosToChange(), TRUE);
                $count = 0;
                foreach ($aInfosToChange as $singleInfoKey => $singleInfoValue) {
                    $aInfosFromStore["fieldSelect_$count"] = $singleInfoKey;
                    if (!preg_match('#\[\[NULL\]\]#', $singleInfoValue)) {
                        $aInfosFromStore["fieldText_$count"] = $singleInfoValue;
                    } elseif (preg_match('#\[\[NULL\]\]#', $singleInfoValue)) {
                        $aInfosFromStore["ignoreValue_$count"] = 1;
                    }
                    $count++;
                }
            }
            $retailerInfoForm = new Crawler_Form_RetailerInfoForm(count($aInfosToChange) + 1);
            $aInfosFromStore['actionSelect'] = $storeInfos->getAction();
            $aInfosFromStore['validityLength'] = $storeInfos->getValidityLength();
            $aInfosFromStore['storeUrl'] = preg_replace(array('#\\\#', '#\[\[COMPANYID\]\]#', '#\[\[STOREID\]\]#'), array('', $storeInfos->getIdCompany(), $storeInfos->getIdStore()), $configIni->retailer->urlpattern);

            $retailerInfoForm->populate($aInfosFromStore);
        }

        $this->view->aRetailerInfos = $aInfos;
        $this->view->retailerInfoForm = $retailerInfoForm;
    }

}
