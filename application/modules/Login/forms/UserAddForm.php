<?php

/**
 * Formular zum Hinzufügen von GUI-Benutzern
 *
 * Class Login_Form_UserAddForm
 */
class Login_Form_UserAddForm extends Zend_Form
{
    public function __construct($idUser = null)
    {
        parent::__construct();
        $this->setName('userAddForm');
        $this->addAttribs(array('role' => 'form'));

        $userId = new Zend_Form_Element_Hidden('userId');
        $userId->setAttribs(array('style' => 'display:none;'));
        $isUpdate = new Zend_Form_Element_Hidden('isUpdate');
        $isUpdate->setAttribs(array('style' => 'display:none;'));

        $eUser = new Marktjagd_Database_Entity_User();
        if ($idUser
           && strlen($idUser)
        ) {
            $eUser->find($idUser);
            $userId->setValue($idUser);
            $isUpdate->setValue('1');
        } else {
            $isUpdate->setValue('0');
        }

        $userName = new Zend_Form_Element_Text('userName');
        $userName->setLabel('Nutzername (vorname.nachname):*')
                  ->setAttribs(
                      array (
                           'class' => 'form-control',
                           'style' => 'width:500px;'
                      ))
                  ->setValue($eUser->getUserName())
                  ->addValidator('NotEmpty')
                  ->setAllowEmpty(false)
                  ->addErrorMessage('Bitte einen Nutzernamen eingeben!')
                  ->isRequired(true);

        $realName = new Zend_Form_Element_Text('realName');
        $realName->setLabel('Anzeigename:')
                  ->setAttribs(
                      array (
                          'class' => 'form-control',
                          'style' => 'width:500px;'
                      ))
                  ->setValue($eUser->getRealName());

        $idRole = new Zend_Form_Element_Select('idRole');
        $idRole->setLabel('Benutzerrolle')
               ->setAttribs(
                    array (
                        'class' => 'form-control',
                        'style' => 'width:500px;'
                    ));
        $idRole->setMultiOptions($this->_findAllRoles());

        $passwordGenerate = new Zend_Form_Element_Checkbox('passwordGenerate');
        $passwordGenerate->setLabel('Passwort neu generieren');

        if ($idUser
            && strlen($idUser)
        ) {
            $idRole->setValue($eUser->getIdRole());
        }



        $submit = new Zend_Form_Element_Submit('submit');
        $submit->setLabel('Änderungen speichern')
               ->setAttribs(
                   array (
                       'class' => 'btn btn-success'
                   ));

        $this->addElements(
            array(
                $userId,
                $userName,
                $realName,
                $idRole,
                $passwordGenerate,
                $isUpdate,
                $submit
            )
        );
    }

    protected function _findAllRoles()
    {
        $aRole = array();
        $sRole = new Marktjagd_Database_Service_Role();
        $cRole = $sRole->findAll();

        /* @var $eRole Marktjagd_Database_Entity_Role */
        foreach ($cRole as $eRole) {
            $aRole[$eRole->getIdRole()] = $eRole->getName();
        }

        return $aRole;
    }
}