<?php
class Crawler_LoggingController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->view->headMeta()->appendHttpEquiv('refresh', '10');
        $sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();
        $cRunningProceeses = $sCrawlerLog->findRunningProcesses();
        $cScheduledArticles = $sCrawlerLog->findScheduledProcessesByType(1);
        $cScheduledBrochures = $sCrawlerLog->findScheduledProcessesByType(2);
        $cScheduledStores = $sCrawlerLog->findScheduledProcessesByType(3);

        $this->view->cRunningProcesses = $cRunningProceeses;
        $this->view->cScheduledArticles = $cScheduledArticles;
        $this->view->cScheduledBrochures = $cScheduledBrochures;
        $this->view->cScheduledStores = $cScheduledStores;
    }

    public function finishedAction()
    {
        $archiveForm = new Crawler_Form_LoggingArchiveForm();

        // standardmäßig letzten 3 Tage anzeigen
        $archiveForm->getElement('period')->setValue(3);
        $options = array(
            'period' => '3'
        );
        if ($this->getRequest()->isPost()) {
            if ($archiveForm->isValid($this->getRequest()->getPost())) {
                $params = $this->_request->getParams();

                $archiveForm->populate($params);

                if ($params['type'] > 0) {
                    $options['type'] = $params['type'];
                } else {
                    unset($options['type']);
                }

                if ($params['period'] > 0) {
                    $options['period'] = $params['period'];
                } else {
                    unset($options['period']);
                }

                if (strlen($params['companyId'])) {
                    $options['companyId'] = $params['companyId'];
                } else {
                    unset($options['companyId']);
                }

                if ($params['status'] > 0) {
                    $options['status'] = $params['status'];
                } else {
                    unset($options['status']);
                }
            }
        }

        $this->view->archiveForm = $archiveForm;

        $sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();
        $cCrawlerLog = $sCrawlerLog->findFinished($options);

        $this->view->cCrawlerLog = $cCrawlerLog;
    }

    public function detailAction()
    {
        $idCrawlerLog = $this->_request->getParam('idCrawlerLog');
        
        $sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();
        $eCrawlerLog = $sCrawlerLog->findById($idCrawlerLog);

        $eCrawlerType = new Marktjagd_Database_Entity_CrawlerType();
        $eCrawlerType->find($eCrawlerLog->getCrawlerConfig()->getIdCrawlerType());

        $startCrawlerForm = new Crawler_Form_CrawlerStartForm($eCrawlerLog->getIdCrawlerConfig(), true);
        $this->view->crawlerStart = $startCrawlerForm;

        $this->view->eCrawlerLog = $eCrawlerLog;
        $this->view->type = array(
                                0 => 'ereignisgesteuert / manuell ',
                                1 => 'zeitgesteuert'
                            );

        $this->view->crawlerType = $eCrawlerType->getType();

        /* @var $eCrawlerLogType Marktjagd_Database_Entity_CrawlerLogType */
        switch ($eCrawlerLog->getIdCrawlerLogType()) {
            case '1':
            case '8':
                $status  = 'info';
                break;
            case '2':
            case '4':
            case '10':
                $status  = 'success';
                break;
            case '5':
            case '6':
            case '9':
            case '11':
                $status  = 'warning';
                break;
            case '3':
            case '7':
                $status  = 'danger';
                break;
            default:
                $status = '';
                break;
        }

        $this->view->status = $status;
    }
}