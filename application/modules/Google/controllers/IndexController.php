<?php
/**
 * Class Google_IndexController
 */
class Google_IndexController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    public function mjtogooglecsvAction()
    {
        $googleForm = new Google_Form_GooglePlacesExportForm();
        $this->view->form = $googleForm;

        //Prüfen, ob Formular abgeschickt
        if($this->_request->isPost())
        {
            $formData = $this->_request->getPost();

            // Prüfen ob Formular valide
            if($googleForm->isValid($formData))
            {
                $sGooglePlaces = new Marktjagd_Service_Output_GooglePlaces();
                $file = $sGooglePlaces->generateExportCsvByCompanyId($formData['companyId']);
                $xlsFile = preg_replace('#\.csv#is', '.xls', $file);

                $sExcel = new Marktjagd_Service_Input_PhpExcel();
                $xlsFile = $sExcel->convertCsvToXls($file, $xlsFile, ';');

                // disable the view
                $this->view->layout()->disableLayout();
                $this->_helper->viewRenderer->setNoRender(true);

                // set headers
                header("Content-Type: application/vnd.ms-excel");
                header('Content-Disposition: attachment; filename="googleMyBusiness_' . $formData['companyId'] . '.xls"');

                // initialise download
                readfile($xlsFile);
            } else {
                $googleForm->populate($formData);
            }
        }
    }

    public function googlecsvtomjAction()
    {
        $form = new Google_Form_GooglePlacesImportForm();
        $this->view->form = $form;

        if ($this->_request->isPost()) {
            $formData = $this->_request->getPost();

            if ($form->isValid($formData)) {
                $formValues = $form->getValues();
                $fileName = $form->googleFile->getFileName();
                $aType = array(
                    '0' => 'stores',
                    '1' => 'products'
                );
                if ($formData['googleType'] == '0') {
                $sGoogle = new Marktjagd_Service_Input_GooglePlaces();
                } else {
                    $sGoogle = new Marktjagd_Service_Input_GoogleProducts();
                }
                $file = $sGoogle->generateMjCsv($formValues['companyId'], $fileName);

                $this->view->layout()->disableLayout();
                $this->_helper->viewRenderer->setNoRender(true);

                // set headers
                header("Content-Type: text/csv");
                header('Content-Disposition: attachment; filename="'
                    . $formValues['companyId'] . '_' . $aType[$formData['googleType']] . '_marktjagd.csv"');

                // initialise download
                readfile($file);
            } else {
                $form->populate($formData);
            }
        }
    }

//    public function pdftoimagesAction()
//    {
//        $form = new Google_Form_PdfToImageForm();
//        $this->view->form = $form;
//
//        if ($this->_request->isPost()) {
//            $formData = $this->_request->getPost();
//
//            if ($form->isValid($formData)) {
//                $formValues = $form->getValues();
//                $fileName = $form->pdfFile->getFileName();
//                $dpi = $formValues['dpi'];
//
//                $sPdf = new Marktjagd_Service_Output_Pdf();
//                $destination = $sPdf->extractImages($fileName, $dpi);
//
//                $this->view->layout()->disableLayout();
//                $this->_helper->viewRenderer->setNoRender(true);
//
//                // set headers
//                header("Content-Type: application/zip");
//                header('Content-Disposition: attachment; filename="' . basename($destination));
//
//                // initialise download
//                readfile($destination);
//            } else {
//                $form->populate($formData);
//            }
//        }
//    }
}