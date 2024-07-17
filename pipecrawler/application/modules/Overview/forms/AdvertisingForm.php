<?php

class Overview_Form_AdvertisingForm extends Zend_Form
{

    public function __construct($company = '')
    {
        parent::__construct();

        $this->setName('advertisingForm');
        $this->addAttribs(array('role' => 'form',
            'style' => 'width: 500px',
            'id' => 'adSelect'));

        $companyId = new Zend_Form_Element_Select('adCompanyId');
        $companyId->setLabel('Unternehmen:')
                ->setAttribs(
                        array(
                            'class' => 'form-control',
                            'style' => 'width:500px;'
                ))
                ->addValidator('NotEmpty')
                ->setAllowEmpty(FALSE)
                ->addErrorMessage('Bitte ein Unternehmen auswählen')
                ->isRequired(TRUE)
        ;
        $companyId->setMultiOptions($this->_findAllCompanies())
                ->setValue($company)
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));
        
        $companyAdStart = new Zend_Form_Element_Text('companyAdStart');
        $companyAdStart->addErrorMessage('Bitte Startdatum angeben.')
                ->setLabel('Startdatum:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->setAttribs(array(
                    'class' => 'form-control'
                ))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $companyAdEnd = new Zend_Form_Element_Text('companyAdEnd');
        $companyAdEnd->addErrorMessage('Bitte Enddatum angeben.')
                ->setLabel('Enddatum:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->setAttribs(array(
                    'class' => 'form-control'
                ))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));
        
        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Auswerten!')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $this->addElements(
                array(
                    $companyId,
                    $companyAdStart,
                    $companyAdEnd,
                    $submit
                )
        );
    }

    protected function _findAllCompanies()
    {
        $sRedmine = new Marktjagd_Service_Input_Redmine();
        $aCompany = $sRedmine->getCompanies('DI - manuelle/wiederkehrende Aufgaben');
        
        $aReturn = array(
            0 => 'Unternehmen auswählen...'
        );
        
        foreach ($aCompany as $key => $value) {
            $aReturn[$key] = $value['name'];
        }
        
        return $aReturn;
    }

}
