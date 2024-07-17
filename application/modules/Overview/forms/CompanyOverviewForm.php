<?php

class Overview_Form_CompanyOverviewForm extends Zend_Form
{

    public function __construct()
    {
        parent::__construct();

        $this->setName('companyOverviewForm');
        $this->addAttribs(array('role' => 'form'));

        $product = new Zend_Form_Element_Select('product');
        $product->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Vertragsart:')
                ->setRequired(TRUE);
        $product->setMultiOptions($this->_findAllProducts())
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $companyDetailEnd = new Zend_Form_Element_Text('companyOverviewEnd');
        $companyDetailEnd->addErrorMessage('Bitte Enddatum angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Enddatum:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->setValue(date('d.m.Y'))
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Filtern')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $this->addElements(
                array(
                    $product,
                    $companyDetailEnd,
                    $submit
        ));
    }

    protected function _findAllProducts()
    {
        $sDb = new Marktjagd_Database_Service_Company();
        $aCompanies = $sDb->findAll();
        $aCollectedProducts = array(
            'Alle' => '0|',
            'Akquise' => '1|',
            'Kunde' => '2|',
            'TOP-Händler (Akquise)' => '3|',
            'Light' => '4|');

        $aReducedProducts = array(
            'custom' => 'Kunde',
            'trial' => 'Kunde',
            'acquisition' => 'Akquise',
            'standard' => 'Kunde',
            'break' => 'Kunde',
            'listing' => 'Kunde',
            'light' => 'Light',
            'TOP-Händler (Akquise)' => 'TOP-Händler (Akquise)'
        );

        foreach ($aCompanies as $singleCompany)
        {
            if (!strlen($singleCompany->getProductCategory())
                    || preg_match('#inactive#', $singleCompany->getStatus())
                    || preg_match('#\(UIM\)#', $singleCompany->getName()))
            {
                continue;
            }

            if (strlen($aCollectedProducts['Alle']))
            {
                $aCollectedProducts['Alle'] .= ',';
            }
            $aCollectedProducts['Alle'] .= $singleCompany->getIdCompany();
            if (!array_key_exists($aReducedProducts[$singleCompany->getProductCategory()], $aCollectedProducts))
            {
                $aCollectedProducts[$aReducedProducts[$singleCompany->getProductCategory()]] = $singleCompany->getIdCompany();
                continue;
            }
            $aCollectedProducts[$aReducedProducts[$singleCompany->getProductCategory()]] .= ',' . $singleCompany->getIdCompany();
        }

        $aCollectedProducts = array_flip($aCollectedProducts);

        return $aCollectedProducts;
    }

}
