<?php

class Overview_Form_MarketingForm extends Zend_Form
{

    public function __construct($company = '1')
    {
        parent::__construct();

        $this->setName('marketingForm');
        $this->addAttribs(array('role' => 'form'));

        $companyId = new Zend_Form_Element_Select('companyId');
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

        $brochures = new Zend_Form_Element_Radio('brochures');
        $brochures->setLabel('Welche Standorte?')
                ->setMultiOptions(
                        array(
                            'alle',
                            'mit aktiven Prospekten',
                            'mit aktiven Produkten'
                        )
                )
                ->setValue(array(0))
                ->setAttribs(
                        array(
                            'style' => 'float:left; width:25px; height: 15px;'
                ))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Ab dafür!')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $this->addElements(
                array(
                    $companyId,
                    $brochures,
                    $submit
        ));
    }

    protected function _findAllCompanies()
    {
        $sCompany = new Marktjagd_Database_Service_Company();
        $cCompany = $sCompany->findAll();
        $aCompany = array();
        /* @var $eCompany Marktjagd_Database_Entity_Company */
        foreach ($cCompany as $eCompany)
        {
            if (preg_match('#inactive#', $eCompany->getStatus()))
            {
                continue;
            }
            $aCompany[$eCompany->getIdCompany()] = $eCompany->getIdCompany() . ' - ' . $eCompany->getName();
        }

        return $aCompany;
    }

}
