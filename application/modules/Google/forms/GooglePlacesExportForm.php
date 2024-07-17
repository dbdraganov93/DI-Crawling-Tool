<?php
class Google_Form_GooglePlacesExportForm extends Zend_Form
{
    public function init()
    {
        $this->setAction('')
             ->setMethod('post');
        $this->setAttribs(
            array(
                'enctype' => 'multipart/form-data',
                'role' => 'form',
                'style' => 'width: 200px'
            )
        );

        $companyId = new Zend_Form_Element_Text('companyId');
        $companyId->setLabel('ID des Unternehmens');
        $companyId->setRequired(true)
                  ->setAttribs(
                      array (
                          'class' => 'form-control'
                      ));;

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Google MyBusiness XLS generieren');
        $submit->setAttribs(
            array (
                'class' => 'btn btn-default'
            ));

        $this->addElements(array($companyId, $submit));
    }
}