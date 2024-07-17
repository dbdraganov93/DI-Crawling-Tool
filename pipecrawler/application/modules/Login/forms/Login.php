<?php

/**
 * Loginformular
 *
 * Class Login_Form_Login
 */
class Login_Form_Login extends Zend_Form
{
    public function init()
    {
        $this->setMethod('post');
        $this->setName('loginForm');
        $this->addAttribs(array('role' => 'form'));

        $this->addElement(
            'text', 'userName', array(
                'required' => true,
                'filters'    => array('StringTrim'),
                'attribs' => array (
                    'class' => 'form-control',
                    'placeholder' => 'Benutzername'
                ),
            ));



        $this->addElement('password', 'password', array(
            'required' => true,
            'attribs' => array (
                'class' => 'form-control',
                'placeholder' => 'Passwort'
            ),
        ));

        $this->addElement('submit', 'submit', array(
            'ignore'   => true,
            'label'    => 'Anmelden',
            'attribs' => array ('class' => 'btn btn-lg btn-success btn-block'),
        ));
    }
}