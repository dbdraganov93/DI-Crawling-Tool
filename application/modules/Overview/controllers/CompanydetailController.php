<?php

class Overview_CompanydetailController extends Zend_Controller_Action {

    public function indexAction() {
        $overviewForm = new Overview_Form_CompanyOverviewForm();
        $sStores = new Marktjagd_Database_Service_AmountStores();
        $sQualityCheck = new Marktjagd_Service_Output_QualityCheck();

        $this->view->overviewForm = $overviewForm;

        if ($this->getRequest()->isPost()) {
            if ($overviewForm->isValid($this->getRequest()->getPost())) {
                $params = $this->getRequest()->getPost();
                $aStores = NULL;
                $aProducts = NULL;
                $aBrochures = NULL;
                $aProductId = preg_split('#\s*\|,\s*#', $params['product']);
                $productId = $aProductId[0];
                $aCompaniesToCheck = preg_split('#\s*,\s*#', $aProductId[1]);

                foreach ($aCompaniesToCheck as $singleCompanyToCheck) {
                    $aParams['companyId'] = $singleCompanyToCheck;
                    $aParams['companyDetailStart'] = date('d.m.Y');
                    if (strtotime($params['companyOverviewEnd']) < strtotime(date('d.m.Y'))) {
                        $aParams['companyDetailStart'] = $params['companyOverviewEnd'];
                    }
                    $aParams['companyDetailEnd'] = $params['companyOverviewEnd'];
                    $aBrochures[$singleCompanyToCheck] = NULL;
                    $aProducts[$singleCompanyToCheck] = NULL;
                    if (!is_null($aStores[$singleCompanyToCheck] = $sStores->findByCompanyId($singleCompanyToCheck))) {
                        $aBrochures[$singleCompanyToCheck] = $sQualityCheck->checkAmountInfos($aParams, 'AmountBrochures');
                        $aProducts[$singleCompanyToCheck] = $sQualityCheck->checkAmountInfos($aParams, 'AmountProducts');

                        if (!is_null($aBrochures[$singleCompanyToCheck])) {
                            array_pop($aBrochures[$singleCompanyToCheck]);
                        }

                        if (!is_null($aProducts[$singleCompanyToCheck])) {
                            array_pop($aProducts[$singleCompanyToCheck]);
                        }
                    }
                }
            }

            $this->view->aStores = $aStores;
            $this->view->aBrochures = $aBrochures;
            $this->view->aProducts = $aProducts;
            $this->view->productId = $productId;
            $this->view->overviewForm = $overviewForm;
        }
    }

    public function detailAction() {
        $allParams = $this->_getAllParams();
        $sQualityCheck = new Marktjagd_Service_Output_QualityCheck();
        $startDate = date('d.m.Y');
        $endDate = date('d.m.Y', strtotime('+1week'));
        $companyDetailForm = new Overview_Form_CompanyDetailForm($startDate, $endDate);
        $this->view->companyDetailForm = $companyDetailForm;

        $this->view->aStores = NULL;
        $this->view->aProducts = NULL;
        $this->view->aBrochures = NULL;
        $aCheckedStores = array();
        $aCheckedBrochures = array();
        $aCheckedProducts = array();

        if ($this->_request->isPost()) {
            if ($companyDetailForm->isValid($this->_request->getPost())) {
                $params = $this->_request->getPost();
                $aCheckedStores = $sQualityCheck->checkAmountInfos($params, 'AmountStores', true);
                $aCheckedProducts = $sQualityCheck->checkAmountInfos($params, 'AmountProducts', true);
                $aCheckedBrochures = $sQualityCheck->checkAmountInfos($params, 'AmountBrochures', true);

                $companyDetailForm = new Overview_Form_CompanyDetailForm(
                        $params['companyDetailStart'], $params['companyDetailEnd'], $params['companyId']
                );
            }
            $dateStart = date_create(date('Y-m-d'));
            $dateEnd = date_create($params['companyDetailEnd']);
            $daysUntilFutureDate = (int) date_diff($dateStart, $dateEnd)->format('%R%a');

            if (is_array($aCheckedStores) && $daysUntilFutureDate < 7) {
                array_pop($aCheckedStores);
            }

            if (is_array($aCheckedProducts) && $daysUntilFutureDate < 7) {
                array_pop($aCheckedProducts);
            }
            if (is_array($aCheckedBrochures) && $daysUntilFutureDate < 7) {
                array_pop($aCheckedBrochures);
            }

            $this->view->startDate = $params['companyDetailStart'];
            $this->view->endDate = $params['companyDetailEnd'];
        } else if (array_key_exists('companyId', $allParams)) {
            $params['companyDetailStart'] = date('d.m.Y', $allParams['companyDetailStart']);
            $params['companyDetailEnd'] = date('d.m.Y', $allParams['companyDetailEnd']);
            $params['companyId'] = $allParams['companyId'];
            $aCheckedStores = $sQualityCheck->checkAmountInfos($params, 'AmountStores', true);
            $aCheckedProducts = $sQualityCheck->checkAmountInfos($params, 'AmountProducts', true);
            $aCheckedBrochures = $sQualityCheck->checkAmountInfos($params, 'AmountBrochures', true);

            $companyDetailForm = new Overview_Form_CompanyDetailForm(
                    $params['companyDetailStart'], $params['companyDetailEnd'], $params['companyId']
            );

            $dateStart = date_create(date('Y-m-d'));
            $dateEnd = date_create($params['companyDetailEnd']);
            $daysUntilFutureDate = (int) date_diff($dateStart, $dateEnd)->format('%R%a');

            if (is_array($aCheckedStores) && $daysUntilFutureDate < 7) {
                array_pop($aCheckedStores);
            }

            if (is_array($aCheckedProducts) && $daysUntilFutureDate < 7) {
                array_pop($aCheckedProducts);
            }
            if (is_array($aCheckedBrochures) && $daysUntilFutureDate < 7) {
                array_pop($aCheckedBrochures);
            }

            $this->view->startDate = $params['companyDetailStart'];
            $this->view->endDate = $params['companyDetailEnd'];
        }

        $this->view->aStores = $aCheckedStores;
        $this->view->aProducts = $aCheckedProducts;
        $this->view->aBrochures = $aCheckedBrochures;
        $this->view->companyDetailForm = $companyDetailForm;
    }

    public function errorsAction() {
        $allParams = $this->_getAllParams();
        if (!array_key_exists('changed', $allParams)) {
            Zend_Session::namespaceUnset('errors');
        }
        $sQualityCheckErrors = new Marktjagd_Database_Service_QualityCheckErrors();
        $session = new Zend_Session_Namespace('errors');
        $sUser = new Marktjagd_Database_Service_User();
        $companyErrorForm = new Overview_Form_ErrorForm();
        $this->view->companyErrorForm = $companyErrorForm;
        $aViewErrors = NULL;

        if ($this->_request->isPost()) {
            if ($companyErrorForm->isValid($this->_request->getPost())) {
                $aLimits = new Zend_Config_Ini(APPLICATION_PATH . '/modules/Overview/config/limits.ini');
                $session->errors = NULL;
                $params = $this->_request->getPost();
                $typeToShow = $params['hintType'];
                $aCompaniesToCheck = preg_split('#\s*,\s*#', preg_replace('#^[0-9]+\|,#', '', $params['product']));

                $aErrors = array();

                foreach ($aCompaniesToCheck as $singleCompanyToCheck) {
                    switch ($params['errorType']) {
                        case '1': {
                                $aErrors['stores'] = $sQualityCheckErrors->findByCompanyIdAndType($singleCompanyToCheck, 'stores');
                                break;
                            }
                        case '2': {
                                $aErrors['brochures'] = $sQualityCheckErrors->findByCompanyIdAndType($singleCompanyToCheck, 'brochures');
                                break;
                            }
                        case '3': {
                                $aErrors['products'] = $sQualityCheckErrors->findByCompanyIdAndType($singleCompanyToCheck, 'products');
                                break;
                            }
                        case '4': {
                                $aErrors[$sQualityCheckErrors->findByCompanyIdAndType($singleCompanyToCheck, 'freshness%')->getType()] = $sQualityCheckErrors->findByCompanyIdAndType($singleCompanyToCheck, 'freshness%');
                                break;
                            }
                        case '5': {
                                $aErrors['future brochures'] = $sQualityCheckErrors->findByCompanyIdAndType($singleCompanyToCheck, 'future brochures');
                                break;
                            }
                        case '6': {
                                $aErrors['future products'] = $sQualityCheckErrors->findByCompanyIdAndType($singleCompanyToCheck, 'future products');
                                break;
                            }
                        default: {
                                $aDbErrors = $sQualityCheckErrors->findByCompanyId($singleCompanyToCheck);
                                foreach ($aDbErrors as $singleError) {
                                    $aErrors[$singleError->getType()] = $singleError;
                                }
                            }
                    }

                    if (count($aErrors)) {
                        foreach ($aErrors as $errorKey => $errorValue) {
                            if ($errorValue->getStatus() != 0) {
                                if ($typeToShow == '1' && (preg_match('#freshness#', $errorValue->getType()) || ($errorValue->getActualAmount() == 0 || ($errorValue->getActualAmount() / $errorValue->getLastAmount() < $aLimits->{preg_replace('#future\s*#', '', $errorKey)}->error)))) {
                                    continue;
                                }
                                if ($typeToShow == '2' && !preg_match('#freshness#', $errorValue->getType())) {
                                    if ($errorValue->getActualAmount() / $errorValue->getLastAmount() > $aLimits->{preg_replace('#future\s*#', '', $errorKey)}->error) {
                                        continue;
                                    }
                                }
                                $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['actualAmount'] = $errorValue->getActualAmount();
                                $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['lastAmount'] = $errorValue->getLastAmount();
                                $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['lastTimeModified'] = $errorValue->getLastTimeModified();
                                $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['lastImport'] = $errorValue->getLastImport();
                                $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['status'] = $errorValue->getStatus();
                                $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['id'] = $errorValue->getIdQualityCheckErrors();
                                $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['user'] = $sUser->find($errorValue->getIdUser())->getUserName();

                                if (!preg_match('#freshness#', $errorKey) && $errorValue->getActualAmount() / $errorValue->getLastAmount() > $aLimits->{preg_replace('#future\s*#', '', $errorKey)}->error && $errorValue->getActualAmount() / $errorValue->getLastAmount() <= $aLimits->{preg_replace('#future\s*#', '', $errorKey)}->warning) {
                                    $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['type'] = 2;
                                } else {
                                    $aViewErrors[$errorValue->getIdCompany() . '|' . $errorValue->getCompany()->getName()][$errorKey]['type'] = 1;
                                }
                            }
                        }
                    }
                }
            }
            $session->errors = $aViewErrors;
        }

        $this->view->aErrors = $session->errors;
    }

    public function changeAction() {
        $params = $this->getAllParams();
        $userInfo = Zend_Auth::getInstance()->getStorage()->read();
        $sQualityCheckErrors = new Marktjagd_Database_Service_QualityCheckErrors();
        $sQualityCheckErrors->changeStatus($params['id'], $params['changeType'], $userInfo->idUser);
        $session = new Zend_Session_Namespace('errors');
        $sessionErrors = $session->errors;

        foreach ($sessionErrors as $key => &$singleError) {
            if (is_null($singleError)) {
                continue;
            }
            foreach ($singleError as $detailKey => &$singleErrorDetail) {
                if ($singleErrorDetail['id'] == $params['id']) {
                    if ($params['changeType'] == '0') {
                        unset($singleError[$detailKey]);
                        if (is_null($sessionErrors[$key])) {
                            unset($sessionErrors[$key]);
                        }
                        continue 2;
                    } else {
                        $singleErrorDetail['user'] = $userInfo->userName;
                        $singleErrorDetail['status'] = $params['changeType'];
                    }
                }
            }
        }
        $session->errors = $sessionErrors;

        $this->_helper->redirector('errors', 'companydetail', 'Overview', array('changed' => '1'));
    }

    public function detailoverviewAction() {
        $allParams = $this->_getAllParams();
        $session = new Zend_Session_Namespace('infos');
        if (!array_key_exists('page', $allParams)) {
            $session->infos = NULL;
        }
        $sQualityCheck = new Marktjagd_Service_Output_QualityCheck();
        $startDate = date('d.m.Y');
        $endDate = date('d.m.Y', strtotime('+1week'));
        $aParams['companyDetailStart'] = $startDate;
        $aParams['companyDetailEnd'] = $endDate;
        $detailOverviewForm = new Overview_Form_DetailOverviewForm($startDate, $endDate, $type = '1');
        $sCompanies = new Marktjagd_Database_Service_Company();
        $this->view->detailOverviewForm = $detailOverviewForm;
        $aInfos = NULL;

        if ($this->_request->isPost()) {
            if ($detailOverviewForm->isValid($this->getRequest()->getPost())) {
                $params = $this->_request->getPost();
                $aCompaniesToCheck = preg_split('#\s*,\s*#', $params['product']);

                $dateStart = date_create(date('Y-m-d'));
                $dateEnd = date_create($params['overviewDetailEnd']);
                $daysUntilFutureDate = (int) date_diff($dateStart, $dateEnd)->format('%R%a');

                switch ($params['type']) {
                    case '1': {
                            foreach ($aCompaniesToCheck as $singleCompany) {
                                $eCompany = $sCompanies->find($singleCompany);
                                $aParams['companyId'] = $eCompany->getIdCompany();
                                $aParams['companyDetailStart'] = $params['overviewDetailStart'];
                                $aParams['companyDetailEnd'] = $params['overviewDetailEnd'];

                                if (strtotime(date('d.m.Y')) < strtotime($params['overviewDetailStart'])) {
                                    $params['overviewDetailStart'] = date('d.m.Y');
                                }
                                $info = $sQualityCheck->checkAmountInfos($aParams, 'AmountProducts', true);
                                if ($info) {
                                    $aInfos[$eCompany->getName()] = $info;

                                    if (is_array($aInfos[$eCompany->getName()]) && $daysUntilFutureDate < 7) {
                                        array_pop($aInfos[$eCompany->getName()]);
                                    }
                                }
                            }
                            break;
                        }
                    case '2': {
                            foreach ($aCompaniesToCheck as $singleCompany) {
                                $eCompany = $sCompanies->find($singleCompany);
                                $aParams['companyId'] = $eCompany->getIdCompany();
                                $aParams['companyDetailStart'] = $params['overviewDetailStart'];
                                $aParams['companyDetailEnd'] = $params['overviewDetailEnd'];

                                if (strtotime(date('d.m.Y')) < strtotime($params['overviewDetailStart'])) {
                                    $params['overviewDetailStart'] = date('d.m.Y');
                                }
                                $info = $sQualityCheck->checkAmountInfos($aParams, 'AmountBrochures', true);
                                if ($info) {
                                    $aInfos[$eCompany->getName()] = $info;

                                    if (is_array($aInfos[$eCompany->getName()]) && $daysUntilFutureDate < 7) {
                                        array_pop($aInfos[$eCompany->getName()]);
                                    }
                                }
                            }
                            break;
                        }
                    case '3': {
                            foreach ($aCompaniesToCheck as $singleCompany) {
                                $eCompany = $sCompanies->find($singleCompany);
                                $aParams['companyId'] = $eCompany->getIdCompany();
                                $aParams['companyDetailStart'] = $params['overviewDetailStart'];
                                $aParams['companyDetailEnd'] = $params['overviewDetailEnd'];

                                if (strtotime(date('d.m.Y')) < strtotime($params['overviewDetailStart'])) {
                                    $params['overviewDetailStart'] = date('d.m.Y');
                                }
                                $info = $sQualityCheck->checkAmountInfos($aParams, 'AmountStores', true);
                                if ($info) {
                                    $aInfos[$eCompany->getName()] = $info;

                                    if (is_array($aInfos[$eCompany->getName()]) && $daysUntilFutureDate < 7) {
                                        array_pop($aInfos[$eCompany->getName()]);
                                    }
                                }
                            }
                        }
                }

                $session->infos = $aInfos;
                $session->startDate = $params['overviewDetailStart'];
                $session->endDate = $params['overviewDetailEnd'];
                $session->type = $params['type'];
            }
        }

        $this->view->detailOverviewForm = $detailOverviewForm;
        if (strlen($session->startDate)) {
            $this->view->startDate = $session->startDate;
            $this->view->endDate = $session->endDate;
            $this->view->type = $session->type;
        }

        if (count($session->infos)) {
            $paginator = Zend_Paginator::factory($session->infos);
            if (array_key_exists('page', $allParams)) {
                $paginator->setCurrentPageNumber($allParams['page']);
            }
            $this->view->paginator = $paginator;
        }
    }

    public function marketingAction() {
        $marketingForm = new Overview_Form_MarketingForm();
        $assignmentForm = new Overview_Form_AssignmentForm();
        $calculateDistanceForm = new Overview_Form_CalculateDistanceForm();

        $this->view->marketingForm = $marketingForm;
        $this->view->assignmentForm = $assignmentForm;
        $this->view->calculateDistanceForm = $calculateDistanceForm;

        if ($this->_request->isPost()) {
            $params = $this->_request->getPost();
            if (preg_match('#Ab\s*dafür!#', $params['submit']) && $marketingForm->isValid($this->getRequest()->getPost())) {
                $result = array();

                $aScripts = array(
                    '0' => 'getGeoDataForStore.php',
                    '1' => 'getStoresForBrochures.php',
                    '2' => 'getStoresForProducts.php',
                );

                exec('php ' . APPLICATION_PATH . '/../tools/' . $aScripts[$params['brochures']] . ' ' . $params['companyId'], $result);

                if (count($result) == 1 && preg_match('#([^\/]+?csv)$#', $result[0], $fileNameMatch)) {
                    $filePath = $result[0];
                    $fileName = $fileNameMatch[1];
                    $xlsFilePath = preg_replace('#(\.csv)$#', '.xls', $filePath);
                    $xlsFileName = preg_replace('#(\.csv)$#', '.xls', $fileName);

                    $sExcel = new Marktjagd_Service_Input_PhpExcel();
                    $sExcel->convertCsvToXls($filePath, $xlsFilePath);

                    $this->view->layout()->disableLayout();
                    $this->_helper->viewRenderer->setNoRender(true);

                    header("Content-Type: application/vnd.ms-excel");
                    header('Content-Disposition: attachment; filename="' . $xlsFileName . '"');

                    readfile($xlsFilePath);
                } else {
                    echo $result[0];
                    $this->getResponse()->setHeader('Refresh', '5; URL=https://di-gui.offerista.com/Overview/companydetail/marketing');
                }

                $this->view->marketingForm = $marketingForm;
            } elseif (preg_match('#Absenden#', $params['submit']) && $assignmentForm->isValid($this->getRequest()->getPost())) {
                $result = array();
                exec('php ' . APPLICATION_PATH . '/../tools/getAmountAssignedStoresForBrochures.php ' . strtotime($params['assignmentStart'] . ' 00:00:00') . ' ' . strtotime($params['assignmentEnd'] . ' 23:59:59'), $result);

                if (count($result) == 1 && preg_match('#([^\/]+?csv)$#', $result[0], $fileNameMatch)) {
                    $filePath = $result[0];
                    $fileName = $fileNameMatch[1];
                    $xlsFilePath = preg_replace('#(\.csv)$#', '.xls', $filePath);
                    $xlsFileName = preg_replace('#(\.csv)$#', '.xls', $fileName);

                    $sExcel = new Marktjagd_Service_Input_PhpExcel();
                    $sExcel->convertCsvToXls($filePath, $xlsFilePath);

                    $this->view->layout()->disableLayout();
                    $this->_helper->viewRenderer->setNoRender(true);

                    header("Content-Type: application/vnd.ms-excel");
                    header('Content-Disposition: attachment; filename="' . $xlsFileName . '"');

                    readfile($filePath);
                } else {
                    echo $result[0];
                    $this->getResponse()->setHeader('Refresh', '5; URL=https://di-gui.offerista.com/Overview/companydetail/marketing');
                }
            } elseif (preg_match('#Berechnen#', $params['submit']) && $calculateDistanceForm->isValid($this->getRequest()->getPost())) {
                $result = array();
                exec('php ' . APPLICATION_PATH . '/../tools/calculateDistances.php ' . $params['targetStores'] . ' ' . $params['toCheckStores'] . ' ' . $params['searchDistance'], $result);

                if (count($result) == 1 && preg_match('#([^\/]+?csv)$#', $result[0], $fileNameMatch)) {
                    $filePath = $result[0];
                    $fileName = $fileNameMatch[1];
                    $xlsFilePath = preg_replace('#(\.csv)$#', '.xls', $filePath);
                    $xlsFileName = preg_replace('#(\.csv)$#', '.xls', $fileName);

                    $sExcel = new Marktjagd_Service_Input_PhpExcel();
                    $sExcel->convertCsvToXls($filePath, $xlsFilePath);

                    $this->view->layout()->disableLayout();
                    $this->_helper->viewRenderer->setNoRender(true);

                    header("Content-Type: application/vnd.ms-excel");
                    header('Content-Disposition: attachment; filename="' . $xlsFileName . '"');

                    readfile($xlsFilePath);
                } else {
                    echo $result[0];
                    $this->getResponse()->setHeader('Refresh', '5; URL=https://di-gui.offerista.com/Overview/companydetail/marketing');
                }
            }

            $this->view->calculateDistanceForm = $calculateDistanceForm;
        }
    }

    public function configAction() {
        $overviewConfigForm = new Overview_Form_ConfigForm();
        $sDb = new Marktjagd_Database_Service_QualityCheckCompanyInfos();

        $this->view->overviewConfigForm = $overviewConfigForm;
        $allParams = $this->getAllParams();
        if (array_key_exists('company', $allParams)) {
            $overviewConfigForm = new Overview_Form_ConfigForm($allParams['company']);
            $this->view->overviewConfigForm = $overviewConfigForm;
        }

        if ($this->_request->isPost()) {
            if ($overviewConfigForm->isValid($this->getRequest()->getPost())) {
                $params = $this->_request->getPost();
                if ($params['configCompanyId'] == '0') {
                    $aCompaniesToConfig = preg_split('#\s*,\s*#', preg_replace('#^[0-9]+\|,#', '', $params['product']));
                } else {
                    $aCompaniesToConfig = array($params['configCompanyId']);
                }

                foreach ($aCompaniesToConfig as $singleCompanyToConfig) {
                    $eConfigQA = new Marktjagd_Database_Entity_QualityCheckCompanyInfos();
                    $eConfigQA->setIdCompany($singleCompanyToConfig)
                            ->setBrochures('0')
                            ->setStores('0')
                            ->setProducts('0')
                            ->setFreshnessStores('0')
                            ->setFreshnessBrochures('0')
                            ->setFreshnessProducts('0')
                            ->setFutureBrochures('0')
                            ->setFutureProducts('0');

                    foreach ($params['settings'] as $singleSetting) {
                        switch ($singleSetting) {
                            case 'Standorte': {
                                    $eConfigQA->setStores('1')
                                            ->setLimitStores((float) preg_replace('#,#', '.', $params['storeLimit']));
                                    break;
                                }

                            case 'Prospekte': {
                                    $eConfigQA->setBrochures('1')
                                            ->setLimitBrochures((float) preg_replace('#,#', '.', $params['brochureLimit']));
                                    break;
                                }

                            case 'Produkte': {
                                    $eConfigQA->setProducts('1')
                                            ->setLimitProducts((float) preg_replace('#,#', '.', $params['productLimit']));
                                    break;
                                }
                            case 'Prospekte zukünftig': {
                                    $eConfigQA->setFutureBrochures('1')
                                            ->setLimitBrochures((float) preg_replace('#,#', '.', $params['brochureLimit']));
                                    break;
                                }
                            case 'Produkte zukünftig': {
                                    $eConfigQA->setFutureProducts('1')
                                            ->setLimitProducts((float) preg_replace('#,#', '.', $params['productLimit']));
                                }
                        }
                    }

                    $eConfigQA->setIdQualityCheckCompanyInfos($sDb->findByCompanyId($singleCompanyToConfig)->getIdQualityCheckCompanyInfos());

                    if (array_key_exists('freshness', $params)) {
                        foreach ($params['freshness'] as $singleType) {
                            switch ($singleType) {
                                case 'Standorte': {
                                        $eConfigQA->setFreshnessStores('1');
                                        break;
                                    }
                                case 'Produkte': {
                                        $eConfigQA->setFreshnessProducts('1');
                                        break;
                                    }
                                case 'Prospekte': {
                                        $eConfigQA->setFreshnessBrochures('1');
                                    }
                            }
                        }
                    }

                    $eConfigQA->save();
                }

                $this->view->configCompanyId = $params['configCompanyId'];
                $this->view->settings = $params['settings'];
                $this->view->product = $params['product'];
                $this->view->storeLimit = preg_replace('#\.#', ',', $params['storeLimit']);
                $this->view->brochureLimit = preg_replace('#\.#', ',', $params['brochureLimit']);
                $this->view->productLimit = preg_replace('#\.#', ',', $params['productLimit']);
                if (array_key_exists('freshness', $params)) {
                    $this->view->freshness = $params['freshness'];
                }
                $this->view->overviewConfigForm = $overviewConfigForm;
            }
        }
    }

}
