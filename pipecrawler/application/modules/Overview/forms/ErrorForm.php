<?php

class Overview_Form_ErrorForm extends Zend_Form
{

    public function __construct()
    {
        parent::__construct();

        $this->setName('companyerrorForm');
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

        $weight = new Zend_Form_Element_Select('hintType');
        $weight->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Schweregrad des Fehlers:')
                ->setRequired(TRUE);
        $weight->setMultiOptions(
                        array(
                            0 => 'Alle',
                            1 => 'Warnung',
                            2 => 'Fehler')
                )
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $type = new Zend_Form_Element_Select('errorType');
        $type->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Fehlertyp:')
                ->setRequired(TRUE);
        $type->setMultiOptions(
                        array(
                            0 => 'Alle',
                            1 => 'Standorte',
                            2 => 'Prospekte',
                            3 => 'Produkte',
                            4 => 'Freshness',
                            5 => 'Prospekte zukünftig',
                            6 => 'Produkte zukünftig')
                )
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Filtern')
                ->setValue($this->_findAllProducts())
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $this->addElements(
                array(
                    $product,
                    $weight,
                    $type,
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
            'TOP-Händler (Akquise)' => '3|');

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
