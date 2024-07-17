<?php
class Google_Form_PdfToImageForm extends Zend_Form
{
    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->setName('upload');
        $this->setAttribs(
            array(
                'enctype' => 'multipart/form-data',
                'role' => 'form',
                'style' => 'width: 300px'
            )
        );


        $companyId = new Zend_Form_Element_Text('dpi');
        $companyId->setLabel('DPI - Qualität der Bilder')
                  ->setValue(50);

        $companyId->setRequired(true)
                  ->addValidator('NotEmpty')
                  ->setAttribs(
                    array (
                        'class' => 'form-control'
                    ));

        $pdfFile = new Zend_Form_Element_File('pdfFile');
        $pdfFile->setLabel('PDF-Datei');
        $pdfFile->setDestination(APPLICATION_PATH . '/../public/files/pdf/');
        $pdfFile->addDecorator(array('data' => 'HtmlTag'), array('tag' => 'td'));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('PDF Seiten als Bilder extrahieren');
        $submit->setAttribs(
            array (
                'class' => 'btn btn-default'
            ));

        $this->addElements(
                array(
                    $companyId,
                    $pdfFile,
                    $submit
        ));
    }
}