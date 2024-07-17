<?php

class BrochureMods_Form_BrochureClickoutForm extends Zend_Form
{
    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->setName('clickout');
        $this->setAttribs(
            array(
                'enctype' => 'multipart/form-data',
                'role' => 'form',
                'style' => 'width: 300px'
            )
        );
        $pdfFile = new Zend_Form_Element_File('pdfFile');
        $pdfFile->setLabel('PDF-Datei');
        $pdfFile->setDestination(APPLICATION_PATH . '/../public/files/pdf/');
        $pdfFile->addDecorator(array('data' => 'HtmlTag'), array('tag' => 'td'));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Kommentare in Prospekt durch Clickouts ersetzen');
        $submit->setAttribs(
            array(
                'class' => 'btn btn-default'
            ));

        $this->addElements(
            array(
                $pdfFile,
                $submit
            ));
    }
}