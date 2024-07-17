<?php

/**
 * Formular zum Hinzufügen von FTP-Benutzern
 *
 * Class Ftp_Form_UserAddForm
 */
class Ftp_Form_UserAddForm extends Zend_Form
{
    public function __construct($login = null)
    {
        parent::__construct();
        $eUser = new Marktjagd_Database_Entity_Users();
        $this->setName('crawlerAddForm');
        $this->addAttribs(array('role' => 'form'));

        $loginName = new Zend_Form_Element_Text('loginName');
        $loginName->setLabel('Login-Name od. Company-Id:*')
                  ->setAttribs(
                      array (
                           'class' => 'form-control',
                           'style' => 'width:500px;'
                      ))
                  ->addValidator('NotEmpty')
                  ->setAllowEmpty(false)
                  ->addErrorMessage('Bitte einen Loginnamen eingeben!')
                  ->isRequired(true);
        $isUpdate = new Zend_Form_Element_Hidden('isUpdate');
        if ($login
            && strlen($login)
        ) {
            $eUser->find($login);
            $loginName->setValue($login)
                      ->setOptions(array(
                    'readonly' => 'true'
                ));
            $isUpdate->setValue('1');
        } else {
            $isUpdate->setValue('0');
        }



        $subfolder = new Zend_Form_Element_Text('subfolder');
        $subfolder->setLabel('Unterverzeichnis:')
                  ->setAttribs(
                      array (
                          'class' => 'form-control',
                          'style' => 'width:500px;'
                      ))
                  ->setValue(preg_replace('#/srv/ftp[/]{0,1}#', '', $eUser->getDirectory()));


        $comment = new Zend_Form_Element_Text('comment');
        $comment->setLabel('Kommentar zum Login:')
                ->setAttribs(
                    array (
                        'class' => 'form-control',
                        'style' => 'width:500px;'
                    ))
                ->setValue($eUser->getComment());

        $allowedIps = new Zend_Form_Element_Text('allowedIps');
        $allowedIps->setLabel('Erlaubte IP-Adressen:')
                   ->setAttribs(
                       array (
                           'class' => 'form-control',
                           'style' => 'width:500px;'
                       ))
                   ->setValue($eUser->getAllowedIps());

        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Änderungen speichern')
               ->setAttribs(
                   array (
                       'class' => 'btn btn-success'
                   ));

        $this->addElements(
            array(
                $loginName,
                $subfolder,
                $comment,
                $allowedIps,
                $isUpdate,
                $submit
            )
        );
    }
}