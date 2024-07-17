<?php
/**
 * Controller zum Darstellen, Hinzufügen, Bearbeiten und Löschen von Logins
 *
 * Class Login_UserController
 */
class Login_UserController extends Zend_Controller_Action
{
    public function indexAction() {
        $this->forward('show');
    }

    public function showAction()
    {
        $sUser = new Marktjagd_Database_Service_User();
        $cUser = $sUser->findAll();
        $this->view->users = $cUser;
    }

    public function addAction()
    {
        $params = $this->_request->getParams();

        if (array_key_exists('userId', $params)) {
            $form = new Login_Form_UserAddForm($params['userId']);
            $this->view->isUpdate = '1';
        } else {
            $form = new Login_Form_UserAddForm();
            $this->view->isUpdate = '0';
        }

        $this->view->form = $form;
        if ($this->getRequest()->isPost()) {
            if ($form->isValid($this->getRequest()->getPost())) {
                $params = $this->_request->getParams();

                $userId = '';
                $userName = '';
                $realName = '';

                if (array_key_exists('userId', $params)) {
                    $userId = $params['userId'];
                } else {
                    $params['passwordGenerate'] = '1';
                }

                if (array_key_exists('userName', $params)) {
                    $userName = $params['userName'];
                }

                if (array_key_exists('realName', $params)) {
                    $realName = $params['realName'];
                }

                $isUpdate = false;
                if (array_key_exists('isUpdate', $params)
                    && $params['isUpdate'] == '1'
                ) {
                    $isUpdate = true;
                }

                $eUser = new Marktjagd_Database_Entity_User();
                $eUser->setIdUser($userId)
                      ->setIdRole($params['idRole'])
                      ->setUserName($userName)
                      ->setRealName($realName);

                $ePassword = new Marktjagd_Entity_Password();
                if ($params['passwordGenerate']) {
                    $sGeneratorPassword = new Marktjagd_Service_Generator_Password();
                    $ePassword = $sGeneratorPassword->generateHashedPassword('sha256');

                    $eUser->setPassword($ePassword->getPasswordHashed())
                          ->setPasswordSalt($ePassword->getSalt());
                }

                $message = '';
                $success = true;
                // Update oder Insert
                if ($isUpdate) {
                    if (!$eUser->save()) {
                        $message = 'Fehler beim Aktualisieren des Nutzers aufgetreten:<br />'
                            . print_r($eUser->toArray(), true);
                        $success = false;
                    }
                } else {
                    $mUser = new Marktjagd_Database_Mapper_User();
                    if (!$mUser->insert($eUser)) {
                        $message = 'Fehler beim Anlegen des Nutzers aufgetreten:<br />'
                                               . print_r($eUser->toArray(), true);
                        $success = false;
                    }
                }

                $this->view->action = 'form';


                if ($success) {
                    if ($params['passwordGenerate']) {
                        $message = 'Nutzer-Login (GUI): ' . $userName . '<br>'
                                   . 'Passwort: ' . $ePassword->getPassword();
                    } else {
                        $message = 'Nutzer-Login (GUI): ' . $userName . ' wurde aktualisiert.';
                    }
                }

                $this->view->message = $message;
            }
        }
    }

    public function deleteAction()
    {
        $params = $this->_request->getParams();

        if (array_key_exists('userId', $params)) {
            $eUser = new Marktjagd_Database_Entity_User();
            $eUser->find($params['userId']);

            $sUser = new Marktjagd_Database_Service_User();
            $status = $sUser->deleteUser($params['userId']);

            if ($status) {
                $status = 'success';
            } else {
                $status = 'danger';
            }

            $action = $status?' erfolgreich':' nicht';
            $this->view->message = 'Nutzer "' . $eUser->getUserName() . '"' . $action . ' gel&ouml;scht';
        }
    }

}