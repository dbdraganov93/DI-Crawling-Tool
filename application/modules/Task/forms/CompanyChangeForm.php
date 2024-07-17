<?php

class Task_Form_CompanyChangeForm extends Zend_Form {
    
    public function init() {
        
        $this->addAttribs(array('role' => 'form',
            'id' => 'companyChange'));
        $this->setName('companyChange');
        
        $company = new Zend_Form_Element_Select('company');
        $company->setMultiOptions($this->_findCompanies())
                ->addErrorMessage('Bitte Unternehmen angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width: 500px'
                ))
                ->setLabel('Unternehmen: ')
                ->setAllowEmpty(TRUE)
                ->setRequired(FALSE);
        
        $this->addElement($company);
    }
    
    protected function _findCompanies() {
        $sRedmine = new Marktjagd_Service_Input_Redmine();
        $aCompany = $sRedmine->getCompanies('DI - manuelle/wiederkehrende Aufgaben');

        $aReturn = array();

        foreach ($aCompany as $key => $value) {
            $aReturn[$key] = preg_replace('#integration\s*#is', '', $value['name']);
        }
        
        return $aReturn;       
    }
}