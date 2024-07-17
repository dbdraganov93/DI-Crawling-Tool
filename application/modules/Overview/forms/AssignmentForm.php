<?php

class Overview_Form_AssignmentForm extends Zend_Form {

    public function __construct() {
        parent::__construct();

        $this->setName('assignmentForm');
        $this->addAttribs(array('role' => 'form'));

        $assignmentStart = new Zend_Form_Element_Text('assignmentStart');
        $assignmentStart->addErrorMessage('Bitte Startdatum angeben.')
                ->setAttribs(array(
                    'class' => 'form-control',
                    'style' => 'width:500px;'
                ))
                ->setLabel('Startdatum:')
                ->setAllowEmpty(FALSE)
                ->setRequired(TRUE)
                ->setValue(date('d.m.Y'))
                ->addValidator('NotEmpty')
                ->addDecorator(array('Custom' => 'HtmlTag'), array('tag' => 'div', 'class' => 'form-group'));

        $assignmentEnd = new Zend_Form_Element_Text('assignmentEnd');
        $assignmentEnd->addErrorMessage('Bitte Enddatum angeben.')
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
        $submit->setLabel('Absenden')
                ->setAttribs(
                        array(
                            'class' => 'btn btn-success'
                ))
                ->setDecorators(array(array('ViewHelper')));

        $this->addElements(
                array(
                    $assignmentStart,
                    $assignmentEnd,
                    $submit
        ));
    }

}
