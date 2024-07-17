<?php
class Google_Form_GooglePlacesImportForm extends Zend_Form
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


        $companyId = new Zend_Form_Element_Text('companyId');
        $companyId->setLabel('ID des Unternehmens');
        $companyId->setRequired(true)
                  ->addValidator('NotEmpty')
                  ->setAttribs(
                    array (
                        'class' => 'form-control'
                    ));

        $googlePlacesFile = new Zend_Form_Element_File('googleFile');
        $googlePlacesFile->setLabel('Google Datei');
        $googlePlacesFile->setDestination(APPLICATION_PATH . '/../public/files/googlecsv/import/');
        $googlePlacesFile->addDecorator(array('data' => 'HtmlTag'), array('tag' => 'td'));

        $googleType = new Zend_Form_Element_Radio('googleType');
        $googleType->setLabel('Google Dateityp')
                ->setMultiOptions(
                        array(
                            'Standorte',
                            'Produkte'
                        )
                )
                ->setValue(array(0))
                ->setAttribs(
                        array(
                            'style' => 'float:left; width:25px; height: 15px;'
                ))
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Marktjagd CSV generieren');
        $submit->setAttribs(
            array (
                'class' => 'btn btn-default'
            ));

        $this->addElements(
                array(
                    $companyId,
                    $googlePlacesFile,
                    $googleType,
                    $submit
        ));
    }
}