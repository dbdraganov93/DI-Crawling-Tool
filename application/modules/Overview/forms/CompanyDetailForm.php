<?php

/**
 * Formular zum Hinzufügen einer Crawlerkonfiguration
 *
 * Class Overview_Form_CompanyDetailForm
 */
class Overview_Form_CompanyDetailForm extends Zend_Form
{

    /**
     * Definieren des Formulars über den Konstruktor
     */
    public function __construct($startDate, $endDate, $company = '1')
    {
        parent::__construct();

        $this->setName('companyDetailForm');
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

        $companyDetailStart = new Zend_Form_Element_Text('companyDetailStart');
        $companyDetailStart->addErrorMessage('Bitte Startdatum angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Startdatum:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->setValue($startDate)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $companyDetailEnd = new Zend_Form_Element_Text('companyDetailEnd');
        $companyDetailEnd->addErrorMessage('Bitte Enddatum angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Enddatum:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->setValue($endDate)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $submit = new Zend_Form_Element_Submit('detailSubmit');
        $submit->setLabel('Anzeigen')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $this->addElements(
                array(
                    $companyId,
                    $companyDetailStart,
                    $companyDetailEnd,
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
