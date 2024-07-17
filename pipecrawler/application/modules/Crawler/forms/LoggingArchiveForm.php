<?php

/**
 * Formular zum Filtern von beendeten Crawlern
 *
 * Class Crawler_Form_LoggingArchiveForm
 */
class Crawler_Form_LoggingArchiveForm extends Zend_Form
{
    /**
     * Initialisiert das Formular
     */
    public function init()
    {
        $this->addAttribs(array('role' => 'form'));

        $type = new Zend_Form_Element_Select('type');

        $type->setMultiOptions($this->_findCrawlerType())
             ->setLabel('Crawlertyp:')
             ->setAttribs(
                 array (
                     'class' => 'form-control',
                     'style' => 'width:250px;'
                 ))
             ->addDecorator(array('Custom'  => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $period = new Zend_Form_Element_Select('period');
        $period->setMultiOptions($this->_findPeriod())
               ->setLabel('Zeitraum (seit beendet):')
               ->setAttribs(
                   array (
                       'class' => 'form-control',
                       'style' => 'width:250px;'
                   ))
               ->addDecorator(array('Custom'  => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $companyId = new Zend_Form_Element_Text('companyId');
        $companyId->setLabel('Unternehmens-ID oder -Name:')
                  ->setAttribs(
                      array (
                          'class' => 'form-control',
                          'style' => 'width:250px;'
                      ))
                  ->addDecorator(array('Custom'  => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $status = new Zend_Form_Element_Select('status');
        $status->setMultiOptions($this->_findStatus())
               ->setLabel('Beendigungsstatus:')
               ->setAttribs(
                   array (
                       'class' => 'form-control',
                       'style' => 'width:250px;'
                   ))
               ->addDecorator(array('Custom'  => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));
        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Filtern')
               ->setAttribs(
                   array (
                       'class' => 'btn btn-default'
                   ))
               ->addDecorator(array('Custom'  => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $this->addElements(array($type, $period, $status, $companyId, $submit));

    }

    /**
     * Ermittelt Einträge für Select-Box Crawlertyp
     *
     * @return array
     */
    protected function _findCrawlerType()
    {
        $aCrawlerType = array(
            0 => 'alle',
            1 => 'Artikel',
            2 => 'Prospekte',
            3 => 'Standorte',
            );

        return $aCrawlerType;
    }

    /**
     * Ermittelt Einträge für Select-Box Zeitraum (seit beendet)
     *
     * @return array
     */
    protected function _findPeriod()
    {
        return array(
            '0' => 'unbegrenzt',
            '1' => 'letzter Tag',
            '3' => 'letzten 3 Tage',
            '7' => 'letzte Woche',
            '31' => 'letzter Monat'
        );
    }

    /**
     * Ermittelt Einträge für Select-Box Status
     *
     * @return array
     */
    protected function _findStatus()
    {
        $aStatus = array(
            0 => 'alle',
            3 => 'fehlgeschlagen',
            7 => 'Import konnte nicht gestartet werden',
            9 => 'Import fehlerhaft',
            5 => 'konnte nicht gestartet werden',
            10 => 'erfolgreich',
            4 => 'erfolgreich, kein Import',
        );

        return $aStatus;
    }
}