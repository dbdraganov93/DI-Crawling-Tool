<?php

class Overview_Form_ConfigForm extends Zend_Form
{

    /**
     * Definieren des Formulars über den Konstruktor
     */
    public function __construct($company = '0')
    {
        parent::__construct();

        $this->setName('OverviewConfigForm');
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

        $companyId = new Zend_Form_Element_Select('configCompanyId');
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

        $settings = new Zend_Form_Element_MultiCheckbox('settings');
        $settings->setMultiOptions(array(
                    'Standorte' => 'Standorte',
                    'Prospekte' => 'Prospekte',
                    'Produkte' => 'Produkte',
                    'Prospekte zukünftig' => 'Prospekte zukünftig',
                    'Produkte zukünftig' => 'Produkte zukünftig'))
                ->setValue($this->_findSettings($company))
                ->setAttribs(
                        array(
                            'style' => 'float:left; width:25px; height: 15px;'
                ))
                ->setLabel('Settings')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $storeLimit = new Zend_Form_Element_Text('storeLimit');
        $storeLimit->setLabel('Standort Limit:')
                ->addValidator('float')
                ->setValue($this->_findLimit($company, 'stores'))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $brochureLimit = new Zend_Form_Element_Text('brochureLimit');
        $brochureLimit->setLabel('Prospekt Limit:')
                ->addValidator('float')
                ->setValue($this->_findLimit($company, 'brochures'))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $productLimit = new Zend_Form_Element_Text('productLimit');
        $productLimit->setLabel('Produkt Limit:')
                ->addValidator('float')
                ->setValue($this->_findLimit($company, 'products'))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Ab dafür!')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $freshness = new Zend_Form_Element_MultiCheckbox('freshness');
        $freshness->setMultiOptions(array(
                    'Standorte' => 'Standorte',
                    'Prospekte' => 'Prospekte',
                    'Produkte' => 'Produkte'))
                ->setValue($this->_findFreshness($company))
                ->setAttribs(
                        array(
                            'style' => 'float:left; width:25px; height: 15px;'
                ))
                ->setLabel('Freshness')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $this->addElements(
                array(
                    $product,
                    $companyId,
                    $settings,
                    $storeLimit,
                    $brochureLimit,
                    $productLimit,
                    $freshness,
                    $submit
        ));
    }

    /**
     * Findet alle Unternehmen
     * 
     * @return array $aCompanies
     */
    protected function _findAllCompanies()
    {
        $sCompany = new Marktjagd_Database_Service_Company();
        $cCompany = $sCompany->findAll();
        $aCompany = array('0' => 'Alle');
        /* @var $eCompany Marktjagd_Database_Entity_Company */
        foreach ($cCompany as $eCompany)
        {
            if (preg_match('#active#', $eCompany->getStatus())) {
                $aCompany[$eCompany->getIdCompany()] = $eCompany->getIdCompany() . ' - ' . $eCompany->getName();
            }
        }

        return $aCompany;
    }

    /**
     * Findet angegebenes Typ-Limit
     * 
     * @param string $companyId
     * @param string $type
     * @return float
     */
    protected function _findLimit($companyId, $type)
    {
        $sDb = new Marktjagd_Database_Service_QualityCheckCompanyInfos();
        $aDbSettings = $sDb->findByCompanyId($companyId);

        return preg_replace('#\.#', ',', $aDbSettings->{'getLimit' . ucwords($type)}());
    }

    protected function _findFreshness($companyId)
    {
        $sDb = new Marktjagd_Database_Service_QualityCheckCompanyInfos();
        $aDbSettings = $sDb->findByCompanyId($companyId);

        $aSettings = array();

        if ($aDbSettings->getFreshnessStores() == '1')
        {
            $aSettings['freshnessStores'] = 'Standorte';
        }

        if ($aDbSettings->getFreshnessProducts() == '1')
        {
            $aSettings['freshnessProducts'] = 'Produkte';
        }

        if ($aDbSettings->getFreshnessBrochures() == '1')
        {
            $aSettings['freshnessBrochures'] = 'Prospekte';
        }

        return $aSettings;
    }

    /**
     * Findet zu prüfende Paramter
     * 
     * @param string $companyId
     * @return boolean|array
     */
    protected function _findSettings($companyId)
    {
        $sDb = new Marktjagd_Database_Service_QualityCheckCompanyInfos();
        $aDbSettings = $sDb->findByCompanyId($companyId);

        $aSettings = array();

        if (!is_null($aDbSettings))
        {
            if ($aDbSettings->getStores() == '1')
            {
                $aSettings['Standorte'] = 'Standorte';
            }
            if ($aDbSettings->getBrochures() == '1')
            {
                $aSettings['Prospekte'] = 'Prospekte';
            }
            if ($aDbSettings->getProducts() == '1')
            {
                $aSettings['Produkte'] = 'Produkte';
            }
            if ($aDbSettings->getFutureBrochures() == '1')
            {
                $aSettings['Prospekte zukünftig'] = 'Prospekte zukünftig';
            }
            if ($aDbSettings->getFutureProducts() == '1')
            {
                $aSettings['Produkte zukünftig'] = 'Produkte zukünftig';
            }

            return $aSettings;
        }

        return false;
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
