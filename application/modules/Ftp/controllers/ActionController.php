<?php

/**
 * Controller zum Hinzufügen, Bearbeiten und Löschen von FTP-DB-Einträgen
 */
class Ftp_ActionController extends Zend_Controller_Action
{
    public function showAction()
    {
        $sUser = new Marktjagd_Database_Service_Users();
        $this->view->cUser = $sUser->findAll();

    }

    public function addAction()
    {
        $params = $this->_request->getParams();

        if (array_key_exists('login', $params)) {
            $form = new Ftp_Form_UserAddForm(urldecode($params['login']));
            $this->view->isUpdate = '1';
        } else {
            $form = new Ftp_Form_UserAddForm();
            $this->view->isUpdate = '0';
        }

        $this->view->form = $form;
        if ($this->getRequest()->isPost()) {
            if ($form->isValid($this->getRequest()->getPost())) {
                $params = $this->_request->getParams();
                $login  = $params['loginName'];
                $this->view->login = $login;
                $subfolder = '';
                $comment = '';
                $allowedIps = '';
                $publicKey = '';

                if (array_key_exists('subfolder', $params)) {
                    $subfolder = $params['subfolder'];
                }

                if (array_key_exists('comment', $params)) {
                    $comment = $params['comment'];
                }

                if (array_key_exists('allowedIps', $params)) {
                    $allowedIps = $params['allowedIps'];
                }

                if (array_key_exists('publicKey', $params)) {
                    $publicKey = $params['publicKey'];
                }

                $isUpdate = false;
                if (array_key_exists('isUpdate', $params)
                    && $params['isUpdate'] == '1'
                ) {
                    $isUpdate = true;
                }

                $sUser = new Marktjagd_Database_Service_Users();
                $password = $sUser->generateFtpUserByCompany(
                    $login,
                    $isUpdate,
                    $subfolder,
                    $comment,
                    $allowedIps,
                    $publicKey
                );

                $this->view->action = 'form';
                $this->view->message = 'Unternehmens-ID: '
                    . preg_replace('#uim/#', '', $login) . '<br>'
                    . 'FTP-Login: ' . $login . '<br>'
                    . 'FTP-Passwort: ' . $password;
            }
        }
    }

    public function deleteAction()
    {
        $params = $this->_request->getParams();

        if (array_key_exists('login', $params)) {
            $sUser = new Marktjagd_Database_Service_Users();
            $status = $sUser->deleteUser(urldecode($params['login']));

            if ($status) {
                $status = 'success';
            } else {
                $status = 'danger';
            }

            $action = $status?' erfolgreich':' nicht';
            $this->view->message = 'Nutzer "' . $params['login'] . '"' . $action . ' gel&ouml;scht';
        }
    }
}