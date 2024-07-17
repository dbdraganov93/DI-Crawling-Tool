<?php

class Task_Form_TaskChangeForm extends Zend_Form {

    public function __construct($company = null, $idAd = null) {
        parent::__construct();
        $this->addAttribs(array('role' => 'form',
            'style' => 'width: 500px',
            'id' => 'taskChange'));
        $this->setName('taskChange');
        
        $type = new Zend_Form_Element_Select('taskType');
        $type->setMultiOptions(
                array(
                    'Aufgabe',
                    'Werbeplan'
                ))
                ->addErrorMessage('Bitte Typ angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width: 240px; float:left; margin-right: 20px;'
                ))
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->setDecorators(array(
                    array('ViewHelper'),
                    array('Errors')))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));
        
        $adType = new Zend_Form_Element_Select('adType');
        $adType->setMultiOptions(
                array(
                    'Prospekt',
                    'Produkt'
                ))
                ->addErrorMessage('Bitte zu überprüfenden Typ angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width: 240px;'
                ))
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->setDecorators(array(
                    array('ViewHelper'),
                    array('Errors')))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $taskStart = new Zend_Form_Element_Text('taskStart');
        $taskStart->addErrorMessage('Bitte Startdatum angeben.')
                ->setAttribs(array(
                    'class' => 'form-control'
                ))
                ->setLabel('Start-/ nächstes Datum:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));
        
        $taskEnd = new Zend_Form_Element_Text('taskEnd');
        $taskEnd->addErrorMessage('Bitte Enddatum angeben.')
                ->setAttribs(array(
                    'class' => 'form-control'
                ))
                ->setLabel('Enddatum:')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $intervallLength = new Zend_Form_Element_Text('intervallLength');
        $intervallLength->addErrorMessage('Bitte Intervalllänge angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width: 240px; float: left; margin-right: 20px;',
                ))
                ->setLabel('Intervall:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->setDecorators(array(
                    array('ViewHelper'),
                    array('Errors'),
                    array('Label',
                        array('tag' => 'div'))
        ));

        $companyId = new Zend_Form_Element_Hidden('company');
        $companyId->setAttribs(array(
            'style' => 'clear: both'
        ))
                ->setValue($company);
        
        $user = new Zend_Form_Element_Select('assignedTo');
        $user->setMultiOptions($this->_findAllUsers())
                ->setLabel('zugewiesen an:')
                ->setAttribs(
                        array(
                            'class' => 'form-control',
                            'style' => 'width:500px;'
                ))
                ->isRequired(true);
        $user->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));
        
        $intervallType = new Zend_Form_Element_Select('intervallType');
        $intervallType->setMultiOptions($this->_findIntervalls())
                ->addErrorMessage('Bitte Intervalltyp angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width: 240px; float: left;'
                ))
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->setDecorators(array(
                    array('ViewHelper'),
                    array('Errors')))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $weekDays = new Zend_Form_Element_MultiCheckbox('weekDays');
        $weekDays->addErrorMessage('Bitte Wochentage angeben.')
                ->setLabel('Wochentage:')
                ->setMultiOptions(array(
                    'Montag',
                    'Dienstag',
                    'Mittwoch',
                    'Donnerstag',
                    'Freitag',
                    'Samstag',
                    'Sonntag'
                )
                    )
                ->setValue(NULL)
                ->setAttribs(
                        array(
                            'style' => 'float:left; width:25px; height: 15px;'))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));
        
        
        $taskTitle = new Zend_Form_Element_Text('title');
        $taskTitle->addErrorMessage('Bitte Titel angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width: 500px;'
                ))
                ->setLabel('Aufgabentitel:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $taskDescription = new Zend_Form_Element_Textarea('description');
        $taskDescription->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width: 500px; resize: none;',
                    'rows' => '5'
                ))
                ->setLabel('Aufgabenbeschreibung:')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));
        
        $ticketCheck = new Zend_Form_Element_Checkbox('ticketCheck');
        $ticketCheck->setLabel('Ticket anlegen?');

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Aufgabe anlegen')
                ->setValue($company)
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
        
        $this->addElements(array(
            $type,
            $adType,
            $taskStart,
            $taskEnd,
            $intervallLength,
            $intervallType,
            $companyId,
            $weekDays,
            $taskTitle,
            $taskDescription,
            $user,
            $ticketCheck,
            $submit,
            $abort
        ));
    }

    protected function _findIntervalls() {
        $aDateTranslate = array(
            'day' => 'Tag(e)',
            'week' => 'Woche(n)',
            'month' => 'Monat(e)',
            'year' => 'Jahr(e)',
            'unique' => 'einmalig'
        );
        $sDb = new Marktjagd_Database_DbTable_Task();
        $aDbIntervall = $sDb->getIntervallTypes();
        $aIntervall = array('choose' => 'Intervalltyp auswählen...');
        foreach ($aDbIntervall as $aDbIntervallValue)
        {
            $aIntervall[$aDbIntervallValue] = $aDateTranslate[$aDbIntervallValue];
        }
        
        return $aIntervall;
    }
    
    protected function _findAllUsers()
    {
        $sUser = new Marktjagd_Database_Service_User();
        $cUser = $sUser->findAll();

        $aAuthor = array(0 => 'keinen');
        /* @var $eAuthor Marktjagd_Database_Entity_User */
        foreach ($cUser as $eUser)
        {
            $aAuthor[$eUser->getIdUser()] = $eUser->getUserName();
        }

        return $aAuthor;
    }
}
