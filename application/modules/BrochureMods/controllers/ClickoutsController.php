<?php

class BrochureMods_ClickoutsController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    public function commentAction()
    {
        $form = new BrochureMods_Form_BrochureClickoutForm();
        $this->view->form = $form;

        file_put_contents(APPLICATION_PATH . '/../public/files/pdf/foo.log', "comments clickout action started.\n");
        if ($this->_request->isPost()) {
            file_put_contents(APPLICATION_PATH . '/../public/files/pdf/foo.log', "post request sent.\n", FILE_APPEND);
            $formData = $this->_request->getPost();

            if ($form->isValid($formData)) {
                file_put_contents(APPLICATION_PATH . '/../public/files/pdf/foo.log', "post request valid.\n", FILE_APPEND);
                $formValues = $form->getValues();

                file_put_contents(APPLICATION_PATH . '/../public/files/pdf/foo.log', $formValues['pdfFile'], FILE_APPEND);

                $pdfFilePath = APPLICATION_PATH . '/../public/files/pdf/' . $formValues['pdfFile'];

                if (!strlen($pdfFilePath)) {
                    file_put_contents(APPLICATION_PATH . '/../public/files/pdf/foo.log', "no pdf-file found.\n", FILE_APPEND);
                } else {
                    file_put_contents(APPLICATION_PATH . '/../public/files/pdf/foo.log', "pdf-file $pdfFilePath found.\n", FILE_APPEND);
                }

                $sPdf = new Marktjagd_Service_Output_Pdf();

                $pdfFileExchanged = $sPdf->exchange($pdfFilePath);
                if (!is_string($pdfFileExchanged)) {
                    echo $pdfFileExchanged;
                    $this->getResponse()->setHeader('Refresh', '5; URL=https://di-gui.offerista.com/BrochureMods/clickouts/comment.phtml');
                }
                $this->view->layout()->disableLayout();
                $this->_helper->viewRenderer->setNoRender(true);

                // set headers
                header("Content-Type: application/pdf");
                header('Content-Disposition: attachment; filename="' . basename($pdfFileExchanged));

                // initialise download
                readfile($pdfFileExchanged);
            } else {
                file_put_contents(APPLICATION_PATH . '/../public/files/pdf/foo.log', 'an error occured during form validation.', FILE_APPEND);
            }
        }
    }
}