<?php

class Overview_Form_DetailOverviewForm extends Zend_Form
{

    /**
     * Definieren des Formulars über den Konstruktor
     */
    public function __construct($startDate, $endDate, $type)
    {
        parent::__construct();

        $this->setName('detailOverviewForm');
        $this->addAttribs(array('role' => 'form'));

        $product = new Zend_Form_Element_Select('product');
        $product->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Vertragsart:')
                ->setRequired(TRUE);
        $product->setMultiOptions($this->_findAllProducts())
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'))
                ->setValue('');

        $typeInfo = new Zend_Form_Element_Select('type');
        $typeInfo->setLabel('Typ:')
                ->setAttribs(
                        array(
                            'class' => 'form-control',
                            'style' => 'width:500px;'
                ))
                ->addValidator('NotEmpty')
                ->setAllowEmpty(FALSE)
                ->addErrorMessage('Bitte einen Info-Typ auswählen')
                ->isRequired(TRUE)
        ;
        $typeInfo->setMultiOptions($this->_findAllCrawlerTypes())
                ->setValue($type)
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $typeStart = new Zend_Form_Element_Text('overviewDetailStart');
        $typeStart->addErrorMessage('Bitte Startdatum angeben.')
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

        $typeEnd = new Zend_Form_Element_Text('overviewDetailEnd');
        $typeEnd->addErrorMessage('Bitte Enddatum angeben.')
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
                    $typeInfo,
                    $typeStart,
                    $typeEnd,
                    $submit
        ));
    }

    protected function _findAllCrawlerTypes()
    {
        $aTypes = array(
            'articles' => 'Produkte',
            'stores' => 'Standorte',
            'brochures' => 'Prospekte'
        );

        $sCrawlerType = new Marktjagd_Database_Service_CrawlerType();
        $cCrawlerType = $sCrawlerType->findAll();
        $aCrawlerType = array();
        /* @var $eCrawlerType Marktjagd_Database_Entity_CrawlerType */
        foreach ($cCrawlerType as $eCrawlerType)
        {
            $aCrawlerType[$eCrawlerType->getIdCrawlerType()] = $aTypes[$eCrawlerType->getType()];
        }

        return $aCrawlerType;
    }

    protected function _findAllProducts()
    {
        $sDb = new Marktjagd_Database_Service_Company();
        $aCompanies = $sDb->findAll();
        $aCollectedProducts = array('Alle' => '');
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
