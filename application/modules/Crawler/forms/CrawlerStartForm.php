<?php
class Crawler_Form_CrawlerStartForm extends Zend_Form
{
    public function __construct($crawlerConfigId, $restart = false)
    {
        parent::__construct();
        $this->setAction('/Crawler/crawler/crawlerstart/')
             ->setMethod('GET');
        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Crawler starten')
               ->setAttribs(
                   array (
                       'class' => 'btn btn-warning'
                   ))
                ->setDecorators(array(array('ViewHelper')));

        if ($restart) {
            $submit->setLabel('Crawler neustarten');
        }

        $idCrawlerConfig  = new Zend_Form_Element_Hidden('idCrawlerConfig');
        $idCrawlerConfig->setValue($crawlerConfigId);


        $this->addElements(
            array(
                $idCrawlerConfig,
                $submit,
            ));
    }
}