<?php

class Overview_AdvertisingController extends Zend_Controller_Action
{

    public function indexAction()
    {
        $adForm = new Overview_Form_AdvertisingForm();

        $this->view->advertisingForm = $adForm;

        if ($this->getRequest()->isPost()) {
            if ($adForm->isValid($this->getRequest()->getPost())) {
                $params = $this->getRequest()->getPost();
                $sAdAnalysis = new Marktjagd_Database_Service_AdAnalysis();
                $aBrochuresChecked = array();
                $aProductsChecked = array();

                $dateStart = date('n', strtotime($params['companyAdStart']));
                $dateEnd = date('n', strtotime($params['companyAdEnd']));
                $monthsDifference = $dateEnd - $dateStart;

                if ($monthsDifference < 0) {
                    $monthsDifference = 12 + $monthsDifference;
                }

                $aData = $sAdAnalysis->findAdsForCompanyByIdAndTimeAndType($params['adCompanyId'], strtotime($params['companyAdStart']), strtotime($params['companyAdEnd'] . ' 23:59:59'));

                for ($i = date('n', strtotime($params['companyAdStart'])); $i <= (date('n', strtotime($params['companyAdStart'])) + $monthsDifference); $i++) {
                    $month = (int) $i;
                    if ($month > 12) {
                        $month = $month - 12;
                    }

                    $aBrochuresChecked[$month] = array();

                    $monthName = DateTime::createFromFormat('!m', $month)->format('F');

                    for ($j = 1; $j <= date('t', strtotime($monthName)); $j++) {
                        $aBrochuresChecked[$month][$j] = 'kA';
                        foreach ($aData as $singleData) {
                            if (preg_match('#-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($j, 2, '0', STR_PAD_LEFT) . '$#', $singleData->getTimeChecked())) {
                                $aBrochuresChecked[$month][$j] = $singleData->getCurrentAd() - $singleData->getTargetAd();
                            }
                        }
                    }
                }

                $this->view->brochuresChecked = $aBrochuresChecked;

                $aData = $sAdAnalysis->findAdsForCompanyByIdAndTimeAndType($params['adCompanyId'], strtotime($params['companyAdStart']), strtotime($params['companyAdEnd'] . ' 23:59:59'), 'products');

                for ($i = date('n', strtotime($params['companyAdStart'])); $i <= (date('n', strtotime($params['companyAdStart'])) + $monthsDifference); $i++) {
                    $month = (int) $i;
                    if ($month > 12) {
                        $month = $month - 12;
                    }

                    $aProductsChecked[$month] = array();

                    $monthName = DateTime::createFromFormat('!m', $month)->format('F');

                    for ($j = 1; $j <= date('t', strtotime($monthName)); $j++) {
                        $aProductsChecked[$month][$j] = 'kA';
                        foreach ($aData as $singleData) {
                            if (preg_match('#-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($j, 2, '0', STR_PAD_LEFT) . '$#', $singleData->getTimeChecked())) {
                                $aProductsChecked[$month][$j] = $singleData->getCurrentAd() - $singleData->getTargetAd();
                            }
                        }
                    }
                }

                $this->view->productsChecked = $aProductsChecked;
            }
        }
    }

}
