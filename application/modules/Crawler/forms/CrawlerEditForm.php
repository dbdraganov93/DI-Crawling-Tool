<?php

class Crawler_Form_CrawlerEditForm extends Zend_Form
{

    /**
     * @param Marktjagd_Database_Entity_CrawlerConfig $eCrawlerConfig
     * @param int $userLevel
     */
    public function __construct($eCrawlerConfig, $userLevel)
    {
        parent::__construct();

        $sTriggerConfigFtp = new Marktjagd_Database_Service_TriggerConfig();
        $eTriggerConfig = $sTriggerConfigFtp->findByCrawlerConfigId($eCrawlerConfig->getIdCrawlerConfig());

        $this->setName('crawlerEditForm');
        $this->addAttribs(array('role' => 'form'));


        $companyId = new Zend_Form_Element_Hidden('companyId');
        $companyId->setValue($eCrawlerConfig->getIdCompany())
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'style' => 'display:none'));

        $companyName = new Zend_Form_Element_Hidden('companyName');
        $companyName->setValue($eCrawlerConfig->getCompany()->getName())
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'style' => 'display:none'));

        $crawlerType = new Zend_Form_Element_Hidden('crawlerType');
        $crawlerType->setValue($eCrawlerConfig->getCrawlerType()->getType())
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'style' => 'display:none'));

        $pathToFile = new Zend_Form_Element_Text('pathToFile');
        $pathToFile->setValue($eCrawlerConfig->getFileName())
            ->setLabel('Pfad zur Crawlerdatei:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $behaviour = new Zend_Form_Element_Select('behaviour');
        $behaviour->setMultiOptions($this->_findAllBehaviours())
            ->setLabel('Crawler Behaviour:')
            ->setValue($eCrawlerConfig->getIdCrawlerBehaviour())
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $status = new Zend_Form_Element_Select('status');
        $status->setMultiOptions(array('zeitgesteuert' => 'zeitgesteuert', 'deaktiviert' => 'deaktiviert', 'manuell / auslösergesteuert' => 'manuell / auslösergesteuert'))
            ->setLabel('Status:')
            ->setValue($eCrawlerConfig->getCrawlerStatus())
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $statusChanged = new Zend_Form_Element_Note('statusChanged');
        $statusChanged->setValue($eCrawlerConfig->getStatusChanged(true))
            ->setLabel('Letzte Statusänderung:')
            ->setAttribs(
                array(
                    'class' => 'form-control-static'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $author = new Zend_Form_Element_Select('author');
        $author->setMultiOptions($this->_findAllAuthor())
            ->setLabel('letzter Crawler-Autor:')
            ->setValue($eCrawlerConfig->getIdAuthor())
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $description = new Zend_Form_Element_Textarea('description');
        $description->setValue($eCrawlerConfig->getDescription())
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'rows' => '5',
                    'style' => 'width: 500px'
                ))
            ->setLabel('Beschreibung:')
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $errorMessage = new Zend_Form_Element_MultiCheckbox('errorMessage');
        $errorMessage->setLabel('Benachrichtigung im Fehlerfall:')
            ->setMultiOptions($this->_findAllErrorMessageTypes())
            ->setValue($this->_findCheckedErrorMessageTypes($eCrawlerConfig))
            ->setAttribs(
                array(
                    'style' => 'float:left; width:25px; height: 15px;'))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $system = new Zend_Form_Element_Radio('system');
        $system->setLabel('Auf welchem System läuft das Skript?')
            ->setMultiOptions(
                array(
                    'aws' => 'AWS',
                    'crawler' => 'Crawler-Server'
                )
            )
            ->setValue($eCrawlerConfig->getSystemRunning())
            ->setAllowEmpty(false)
            ->addErrorMessage('Bitte ein System auswählen')
            ->isRequired(true);
        $system->setAttribs(
            array(
                'style' => 'float:left; width:25px; height: 15px;'));
        $system->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $execution = new Zend_Form_Element_Text('execution');
        $execution->setValue($eCrawlerConfig->getExecution())
            ->setLabel('Ausführungszeitpunkt (Cron):')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'))
            ->setDescription('<code>* * * * *<br>' .
                '┬ ┬ ┬ ┬ ┬<br>' .
                '│ │ │ │ └──── Wochentag (0-7, Sonntag ist 0 oder 7)<br>' .
                '│ │ │ └────── Monat (1-12)<br>' .
                '│ │ └──────── Tag (1-31)<br>' .
                '│ └────────── Stunde (0-23)<br>' .
                '└──────────── Minute (0-59)<br>' .
                '</code>');
        $execution->getDecorator('Description')->setOption('escape', false);

        $runtime = new Zend_Form_Element_Note('runtime');
        $runtime->setValue($eCrawlerConfig->getRuntime())
            ->setLabel('Laufzeit (Minuten):')
            ->setAttribs(
                array(
                    'class' => 'form-control-static',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $lastModified = new Zend_Form_Element_Note('lastModified');
        $lastModified->setValue($eCrawlerConfig->getLastModified(true))
            ->setLabel('Letzte Änderung an Einstellungen:')
            ->setAttribs(
                array(
                    'class' => 'form-control-static',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));


        $triggerType = new Zend_Form_Element_Select('triggerType');
        $triggerType->setMultiOptions($this->_findAllTriggerTypes())
            ->setLabel('Triggertyp:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $triggerPattern = new Zend_Form_Element_Text('triggerPattern');
        $triggerPattern->setLabel('Triggerpattern:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $idTriggerConfig = new Zend_Form_Element_Hidden('idTriggerConfig');

        if ($eTriggerConfig->getIdTriggerConfig() != '') {
            $triggerType->setValue($eTriggerConfig->getIdTriggerType());
            $triggerPattern->setValue($eTriggerConfig->getPatternFileName());
            $idTriggerConfig->setValue($eTriggerConfig->getIdTriggerConfig());
        }

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Änderungen speichern')
            ->setAttribs(
                array(
                    'class' => 'btn btn-success'
                ))
            ->setDecorators(array(array('ViewHelper')));

        $abort = new Zend_Form_Element_Reset('abort');
        $abort->setLabel('Abbrechen')
            ->setAttribs(
                array(
                    'class' => 'btn btn-danger'
                ))
            ->setDecorators(array(array('ViewHelper')));

        if ($userLevel > 50) {
            $crawlerType->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
            $pathToFile->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
            $behaviour->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
            $status->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
            $triggerType->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
            $triggerPattern->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
            $author->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
            $description->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
            $execution->setAttribs(
                array(
                    'disable' => 'true',
                    'readonly' => 'true'
                ));
        }

        $this->addElements(
            array(
                $companyId,
                $companyName,
                $crawlerType,
                $pathToFile,
                $behaviour,
                $status)
        );
        if ($eCrawlerConfig->getStatusChanged(true) && $eCrawlerConfig->getStatusChanged(true) != '0000-00-00 00:00:00'
        ) {
            $this->addElement($statusChanged);
        }

        $this->addElements(
            array(
                $triggerType,
                $triggerPattern,
                $idTriggerConfig,
                $author,
                $description,
                $errorMessage,
                $system,
                $execution,
                $runtime,
                $lastModified
            )
        );

        if ($userLevel <= 50) {
            $this->addElements(
                array(
                    $submit,
                    $abort
                )
            );
        }
    }

    /**
     * Ermittelt alle Importverhalten für die Select-Box
     *
     * @return array
     */
    protected function _findAllBehaviours()
    {
        $sBehaviour = new Marktjagd_Database_Service_CrawlerBehaviour();
        $cBehaviour = $sBehaviour->findAll();

        $aBehaviour = array();
        /* @var $eBehaviour Marktjagd_Database_Entity_CrawlerBehaviour */
        foreach ($cBehaviour as $eBehaviour) {
            $aBehaviour[$eBehaviour->getIdCrawlerBehaviour()] = $eBehaviour->getBehaviour();
        }

        return $aBehaviour;
    }

    /**
     * Ermittelt alle Status für die Select-Box
     *
     * @return array
     */
    protected function _findAllStatus()
    {
        $sStatus = new Marktjagd_Database_Service_Status();
        $cStatus = $sStatus->findAll();

        $aStatus = array();
        /* @var $eStatus Marktjagd_Database_Entity_Status */
        foreach ($cStatus as $eStatus) {
            $aStatus[$eStatus->getIdStatus()] = $eStatus->getStatusName();
        }

        return $aStatus;
    }

    /**
     * Ermittelt alle Autoren für die Select-Box
     *
     * @return array
     */
    protected function _findAllAuthor()
    {
        $sAuthor = new Marktjagd_Database_Service_Author();
        $cAuthor = $sAuthor->findAll();

        $aAuthor = array();
        /* @var $eAuthor Marktjagd_Database_Entity_Author */
        foreach ($cAuthor as $eAuthor) {
            $aAuthor[$eAuthor->getIdAuthor()] = $eAuthor->getFirstName() . ' ' . $eAuthor->getLastName();
        }

        return $aAuthor;
    }

    /**
     * Ermittelt alle Triggertypen für die Select-Box
     *
     * @return array
     */
    protected function _findAllTriggerTypes()
    {
        $sTriggerType = new Marktjagd_Database_Service_TriggerType();
        $cTriggerType = $sTriggerType->findAll();
        $aTriggerType = array('null' => 'kein aktiver Trigger');
        /* @var $eTriggerType Marktjagd_Database_Entity_TriggerType */
        foreach ($cTriggerType as $eTriggerType) {
            $aTriggerType[$eTriggerType->getIdTriggerType()] = $eTriggerType->getName();
        }

        return $aTriggerType;
    }

    /**
     * Ermittelt alle Benachrichtigungstypen für die Multicheckbox
     *
     * @return array
     */
    protected function _findAllErrorMessageTypes()
    {
        $aErrorMessage['ticket'] = 'Ticket';
        return $aErrorMessage;
    }

    /**
     * Ermittelt ausgewählte Benachrichtigungstypen für die Multicheckbox
     *
     * @param Marktjagd_Database_Entity_CrawlerConfig $eCrawlerConfig
     * @return array
     */
    protected function _findCheckedErrorMessageTypes($eCrawlerConfig)
    {
        $aCheckedErrorMessage = array();
        if ($eCrawlerConfig->getTicketCreate()) {
            $aCheckedErrorMessage[] = 'ticket';
        }
        return $aCheckedErrorMessage;
    }

}
