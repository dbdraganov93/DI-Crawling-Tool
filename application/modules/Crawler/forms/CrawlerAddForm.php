<?php

/**
 * Formular zum Hinzufügen einer Crawlerkonfiguration
 *
 * Class Crawler_Form_CrawlerAddForm
 */
class Crawler_Form_CrawlerAddForm extends Zend_Form
{

    /**
     * Definieren des Formulars über den Konstruktor
     */
    public function __construct()
    {
        parent::__construct();

        $this->setName('crawlerAddForm');
        $this->addAttribs(array('role' => 'form'));

        $companyId = new Zend_Form_Element_Select('companyId');
        $companyId->setLabel('Unternehmen:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addValidator('NotEmpty')
            ->setAllowEmpty(false)
            ->addErrorMessage('Bitte ein Unternehmen auswählen')
            ->isRequired(true);
        $companyId->setMultiOptions($this->_findAllCompanies())
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $crawlerType = new Zend_Form_Element_Select('crawlerType');
        $crawlerType->setLabel('Crawlertyp:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addValidator('NotEmpty')
            ->setAllowEmpty(false)
            ->addErrorMessage('Bitte einen Crawlertyp auswählen')
            ->isRequired(true);
        $crawlerType->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $crawlerType->setMultiOptions($this->_findAllCrawlerTypes());

        $pathToFile = new Zend_Form_Element_Text('pathToFile');
        $pathToFile->setLabel('Pfad zur Crawlerdatei:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ));
        $pathToFile->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $behaviour = new Zend_Form_Element_Select('behaviour');
        $behaviour->setMultiOptions($this->_findAllBehaviours())
            ->setLabel('Crawler Behaviour:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addValidator('NotEmpty')
            ->setAllowEmpty(false)
            ->addErrorMessage('Bitte ein Behaviour auswählen')
            ->isRequired(true);
        $behaviour->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $status = new Zend_Form_Element_Select('status');
        $status->setMultiOptions(array('zeitgesteuert' => 'zeitgesteuert', 'deaktiviert' => 'deaktiviert', 'manuell / auslösergesteuert' => 'manuell / auslösergesteuert'))
            ->setLabel('Status:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addValidator('NotEmpty')
            ->setAllowEmpty(false)
            ->addErrorMessage('Bitte einen Status auswählen')
            ->isRequired(true);
        $status->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $author = new Zend_Form_Element_Select('author');
        $author->setMultiOptions($this->_findAllAuthor())
            ->setLabel('Crawler-Autor:')
            ->setAttribs(
                array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
            ->addValidator('NotEmpty')
            ->setAllowEmpty(false)
            ->addErrorMessage('Bitte einen Autor auswählen')
            ->isRequired(true);
        $author->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $description = new Zend_Form_Element_Textarea('description');
        $description->setAttribs(
            array(
                'class' => 'form-control',
                'rows' => '5',
                'style' => 'width: 500px'
            ))
            ->setLabel('Beschreibung:')
            ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $errorMessage = new Zend_Form_Element_MultiCheckbox('errorMessage');
        $errorMessage->setLabel('Benachrichtigung im Fehlerfall:')
            ->setMultiOptions(array('0' => 'Ticket'))
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
            ->setAllowEmpty(false)
            ->addErrorMessage('Bitte ein System auswählen')
            ->isRequired(true);
        $system->setAttribs(
            array(
                'style' => 'float:left; width:25px; height: 15px;'));
            $system->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $execution = new Zend_Form_Element_Text('execution');
        $execution->setLabel('Ausführungszeitpunkt (Cron):')
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

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Crawler anlegen')
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

        $this->addElements(
            array(
                $companyId,
                $crawlerType,
                $pathToFile,
                $behaviour,
                $status,
                $triggerType,
                $triggerPattern,
                $author,
                $description,
                $errorMessage,
                $system,
                $execution,
                $submit,
                $abort
            ));
    }

    /**
     * Ermittelt alle Behaviours
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
     * Ermittelt alle Status
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
     * Ermittelt alle Crawler-Autoren
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
     * Ermittelt alle Companies
     *
     * @return array
     */
    protected function _findAllCompanies()
    {
        $sCompany = new Marktjagd_Database_Service_Company();
        $cCompany = $sCompany->findAll();
        $aCompany = array();
        /* @var $eCompany Marktjagd_Database_Entity_Company */
        foreach ($cCompany as $eCompany) {
            $aCompany[$eCompany->getIdCompany()] = $eCompany->getIdCompany() . ' - ' . $eCompany->getName();
        }

        return $aCompany;
    }

    /**
     * Ermittelt alle Crawlertypen
     *
     * @return array
     */
    protected function _findAllCrawlerTypes()
    {
        $sCrawlerType = new Marktjagd_Database_Service_CrawlerType();
        $cCrawlerType = $sCrawlerType->findAll();
        $aCrawlerType = array();
        /* @var $eCrawlerType Marktjagd_Database_Entity_CrawlerType */
        foreach ($cCrawlerType as $eCrawlerType) {
            $aCrawlerType[$eCrawlerType->getIdCrawlerType()] = $eCrawlerType->getType();
        }

        return $aCrawlerType;
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

}
