<?php

/**
 * Formular zum Auswählen der Tage zur Filterung der instabilen Crawler
 *
 * Class Crawler_Form_CrawlerInstableForm
 */
class Crawler_Form_CrawlerInstableForm extends Zend_Form
{
    /**
     * @param int $valueDay
     */
    public function __construct($valueDay = 90)
    {
        parent::__construct();
        $this->setName('crawlerInstableForm');

        $days  = new Zend_Form_Element_Select('days');
        $aOptions = array(
            '30' => '1 Monat',
            '60' => '2 Monate',
            '90' => '3 Monate',
            '120' => '4 Monate',
            '150' => '5 Monate',
            '180' => '6 Monate',
            '210' => '7 Monate',
            '240' => '8 Monate',
            '270' => '9 Monate',
            '300' => '10 Monate',
            '330' => '11 Monate',
            '365' => '1 Jahr'
        );

        $days->setMultiOptions($aOptions);
        $days->setValue($valueDay)
             ->setLabel('Zeitraum')
             ->setDecorators(array(array('ViewHelper')));
        $this->addElements(
            array(
                $days
            ));
    }
}