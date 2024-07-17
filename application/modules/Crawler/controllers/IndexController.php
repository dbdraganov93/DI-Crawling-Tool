<?php

class Crawler_IndexController extends Zend_Controller_Action {

    public function indexAction() {
        $sCrawlerConfig = new Marktjagd_Database_Service_CrawlerConfig();
        $sCrawlerLog = new Marktjagd_Database_Service_CrawlerLog();

        $aUserCrawlerCount = $sCrawlerConfig->countActiveCrawlerByUser();
        $this->view->aUserCrawlerCount = $aUserCrawlerCount;

        $aCrawlerVersionCount = $sCrawlerConfig->countCrawlerByVersionType();
        $this->view->aCrawlerVersionCount = $aCrawlerVersionCount;

        $aCrawlerTypeCount = $sCrawlerConfig->countCrawlerByType();
        $this->view->aCrawlerTypeCount = $aCrawlerTypeCount;

        $cFinished = $sCrawlerLog->findFinished(
                array(
                    'status' => array(3, 5, 7, 9),
                    'limit' => 10
                )
        );

        $this->view->cFinished = $cFinished;

        $aCrawlerModifiedCount = $sCrawlerConfig->countModifiedByType();
        $this->view->aCrawlerModifiedCount = $aCrawlerModifiedCount;

        $formCrawlerInstable = new Crawler_Form_CrawlerInstableForm();
        $this->view->formCrawlerInstable = $formCrawlerInstable;
        $days = 90;
        if ($this->_request->isPost()) {
            $params = $this->_request->getPost();
            if ($formCrawlerInstable->isValid($params)) {
                $days = $params['days'];
            }
        }

        $aCrawlerInstable = $sCrawlerLog->findInstable($days);
        $this->view->aCrawlerInstable = $aCrawlerInstable;
    }

    public function cliAction() {
        // disable the view &layout
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    public function updatecrawlerAction() {
        exec('php ' . APPLICATION_PATH . '/../scripts/updateCrawler.php 2>&1', $o, $returnVar);
        if (!$returnVar) {
            $this->view->done = 'Update erledigt';
        } else {
            $this->view->done = $o[0];
        }
    }
}
