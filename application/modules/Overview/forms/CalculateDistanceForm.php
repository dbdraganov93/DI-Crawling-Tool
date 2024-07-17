<?php

class Overview_Form_CalculateDistanceForm extends Zend_Form {

    public function __construct() {
        parent::__construct();

        $this->setName('calculateDistanceForm');
        $this->addAttribs(array('role' => 'form'));

        $targetStores = new Zend_Form_Element_Text('targetStores');
        $targetStores->addErrorMessage('Bitte Ziel-Unternehmens-ID angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Ziel-Unternehmens-IDs:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $toCheckStores = new Zend_Form_Element_Text('toCheckStores');
        $toCheckStores->addErrorMessage('Bitte Such-Unternehmens-ID angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Such-Unternehmens-IDs:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $searchDistance = new Zend_Form_Element_Text('searchDistance');
        $searchDistance->addErrorMessage('Bitte Such-Radius angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Suchradius in km:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Berechnen!')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $this->addElements(
                array(
                    $targetStores,
                    $toCheckStores,
                    $searchDistance,
                    $submit
        ));
    }

}
