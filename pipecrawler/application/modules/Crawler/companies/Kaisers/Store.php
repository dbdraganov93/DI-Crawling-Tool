<?php
class Crawler_Company_Kaisers_Store extends Crawler_Generic_Company
{
    protected $_baseUrl = 'https://www.kaisers.de';
    protected $_companyId;

    public function crawl($companyId)
    {
        $this->_companyId = $companyId;
        /* @var $logger Zend_Log */
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        
        $sFtp->connect($companyId);
        $localStoreFile = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#KW\s*' . date('W', strtotime('next week')) . '\.xlsx?$#', $singleFile)) {
                $localStoreFile = $sFtp->downloadFtpToCompanyDir($singleFile, $companyId);
            }
        }
        
        $aData = $sExcel->readFile($localStoreFile, TRUE)->getElement(0)->getData();
        $aZipcodesToSkip = array();
        foreach ($aData as $singleStore) {
            $aZipcodesToSkip[] = $singleStore['PLZ'];
        }

        $payment    = 'Girocard/EC, Maestro, Mastercard, Visa, American Express.';
        $logoKaisers    = 'http://media2.marktjagd.de/geschaeft/Kaisers-Tengelmann-'
            . 'Berlin-Friedrichstrasse:1166301_500x500_orig.png';
        $logoTengelmann = 'http://media1.marktjagd.de/geschaeft/Kaisers-Tengelmann-'
            . 'Berlin-Friedrichstrasse:1166302_500x500_orig.png';
        

        $storeListUrl = $this->_baseUrl . '/index.php?type=89657201&tx_tnmkaisers_ajax%5Baction%5D=locations&tx_tnmkaisers_ajax%5Bformat%5D=JSON';
        $storeDetailUrl = $this->_baseUrl . '/index.php?type=89657201&tx_tnmkaisers_ajax%5B'
            . 'action%5D=showLocation&tx_tnmkaisers_ajax%5Buid%5D=';

        if (!$sPage->open($storeListUrl)) {
            $logger->log('couldn\'t open store list url', Zend_Log::CRIT);
        }

        $page = $sPage->getPage()->getResponseBody();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sText = new Marktjagd_Service_Text_TextFormat();
        
        $cStore = new Marktjagd_Collection_Api_Store();
        $aStores = json_decode($page);
        foreach ($aStores as $eStore) {
            // nicht deutsche SO überspringen, SO 1111 doppelt (1303 hat selbe Adresse)
            if ($eStore->country != 'Deutschland'
                || $eStore->uid == '1111'
                    || in_array($eStore->zip, $aZipcodesToSkip)
            ) {
                continue;
            }

            $apiStore = new Marktjagd_Entity_Api_Store();
            $apiStore->setStoreNumber($eStore->uid)
                     ->setStreet($sText->fixHexUnicode($sAddress->extractAddressPart('street', $eStore->street)))
                     ->setStreetNumber($sAddress->extractAddressPart('streetNumber', $eStore->street))
                     ->setZipcode($eStore->zip)
                     ->setCity($sText->fixHexUnicode($eStore->city))
                     ->setLongitude($eStore->longitude)
                     ->setLatitude($eStore->latitude)
                     ->setPayment($payment)
                     ->setTitle($eStore->name);

            if (preg_match('#Kaiser#', $eStore->name)) {
                $apiStore->setLogo($logoKaisers);
            } else if (preg_match('#Tengelmann#', $eStore->name)) {
                $apiStore->setLogo($logoTengelmann);
            }

            $sPage->open($storeDetailUrl . $eStore->uid);
            $detailPage = $sPage->getPage()->getResponseBody();
            $patternOpenings = '#<dl[^>]*>\s*<dt[^>]*>\s*Öffnungszeiten(.*?)</dl>#';
            if (preg_match($patternOpenings, $detailPage, $aMatchOpenings)) {
                $apiStore->setStoreHours($sTimes->generateMjOpenings(preg_replace('#\s*und\s*vor\s*Feiertagen#', '', $aMatchOpenings[1])));
            }

            $patternFeatures = '#<li[^>]*class="feature[^"]*list[^"]*"[^>]*>\s*(.*?)\s*</li>#';
            if (preg_match_all($patternFeatures, $detailPage, $aMatchesFeatures)) {
                $text = '';
                if ($apiStore->getText() != '') {
                    $text = $apiStore->getText() . ', ';
                }

                $apiStore->setText($text . 'Abteilungen: ' . implode(' ', $aMatchesFeatures[1]));
            }

            $patternPhone = '#<dd>Tel\.*\:*\s*(.*?)\s*</dd>#';
            if (preg_match($patternPhone, $detailPage, $aMatchPhone)) {
                $apiStore->setPhone($sAddress->normalizePhoneNumber($aMatchPhone[1]));
            }

            $distribution = $this->_getDistributionByZip($eStore->zip);
            if (strlen($distribution)) {
                $apiStore->setDistribution($distribution);
            }

            $cStore->addElement($apiStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId, FALSE);
        $fileName = $sCsv->generateCsvByCollection($cStore);

        $crawlerResponse = new Crawler_Generic_Response();
        if ($fileName) {
            $crawlerResponse->setFileName($fileName)
                            ->setIsImport(true)
                            ->setLoggingCode(Crawler_Generic_Response::SUCCESS);
        } else {
            $crawlerResponse->setIsImport(false)
                            ->setLoggingCode(Crawler_Generic_Response::FAILED);
        }

        return $crawlerResponse;
    }


    /**
     * Liefert das Mapping Array für Region -> PLZ
     *
     * @return array
     */
    protected function _getRegionZip()
    {
        return array(
            'TW_VIE' => array(
                45468,45470,45478,45481,45472,45476
            ),
            'K_Hamm' => array(
                59065
            ),
            'K_VIE' => array(
                58119,58642,58636,44797,59065,42389,42369,45549,45468,45470,45133,45147,
                44787,44795,45478,45481,45138,45128,46045,46049,46047,45472,46539,47198,
                45355,45476,47506,51061,51381,42349,42107,50765,42719,51377,42655,51069,
                42329,40885,40489,40668,40474,45219,40883,40479,41564,40235,40210,40233,
                40237,40477,40239,40468,40211,40227,41466,40699,40223,40625,40219,41472,
                40627,41464,40217,40822,51469,51429,50678,51065,50937,50996,50933,51105,
                50968,50969,51109,50389,53121,53757,53225,53229,50226,53117,50997,53177,
                53127,53639,53604,53424,53175,53474,53545,53129,53879,53340,47803,47800,
                47798,47799,47906,47809,40670,47877,47839,41189,40667,41065,40545,41238,
                41199,52249,41379,41812,41334,41372,41179,41366,52428,52134,52070,52066,
                52078,52062,52080,52146,52072,41751,41747,41061,41066,41749,41169,41063,
                42799,40591,40599,40789,51371,40764,40589,40721,47057
            ),
            'K_B' => array(
                13158,13359,10409,13127,16792,13156,13187,13189,13467,13437,13353,13357,
                10437,10249,10405,10119,10435,13088,13355,10551,10559,12685,16321,15366,
                13055,13051,12679,12681,12619,10117,10243,10179,10178,10369,10247,12049,
                10969,10785,10999,10997,12439,12555,15738,12489,15562,10319,10365,12557,
                12621,10318,10315,12587,12487,12435,12524,12437,12107,12349,12099,12051,
                10961,12309,10965,12103,14612,16761,14089,13585,13587,13597,13583,13581,
                13629,13503,14052,13595,14059,14057,10789,10777,10627,10717,10707,10623,
                10779,10719,10625,14532,14467,12169,12203,14109,12163,14169,12209,12167,
                14199,12207,13507,10407,10585,14055,10709,14193,14197,14195,14163,12249,
                14482,14480,12279,13057,12683,13125
            ),
            'T_VM' => array(
                85630,85540,85598,81825,85551,85586,81671,83707,83620,83727,82041,85521,
                83607,82008,85640,83115,83022,82067,81477,80809,80801,81379,80335,82140,
                85435,82152,80636,80997,80992,84416
            ),
            'T_M' => array(
                81673,85630,85540,85598,81825,85604,85625,81827,85551,81927,85586,81679,
                81677,81675,80538,85570,81669,81667,81541,81671,81539,81737,81735,83646,
                83707,83620,83727,83735,83703,83670,83661,82377,83700,82041,85521,83607,
                82008,82054,85640,85635,85579,83435,83080,83471,83115,83022,83209,83324,
                83098,82067,81477,82515,82061,81479,82031,81379,81476,80637,80809,80801,
                80802,80805,80798,80335,80636,80804,80339,80469,81371,81547,81543,80333,
                82319,82211,82237,80337,82229,86919,82205,86316,86157,86154,86159,86695,
                86152,86456,86161,86343,86199,82140,82223,82110,82194,82256,82299,82386,
                82487,82327,82467,82418,82362,80807,85774,85737,85435,85399,85375,85748,
                85716,85354,80939,80689,82152,81247,82166,81245,81377,81241,82131,80687,
                80639,80634,80686,80799,80933,80997,80992,80993,85221,80935,85614,82402
            ),
            'unknown' => array(),
        );
    }

    /**
     * Ermittelt die Distributionen anhand der übergebenen PLZ
     *
     * @param $zipCode
     * @return string
     */
    protected function  _getDistributionByZip($zipCode)
    {
        $logger = Zend_Registry::get('logger');
        /* @var $logger Zend_Log */

        // distribution
        $distribution = '';
        if ($zipCode <= 16999) {
            $distribution = 'Berlin/Umland';
        } else if ($zipCode >= 40000
            && $zipCode <= 59999
        ) {
            $distribution = 'Nordrhein';
        } else if ($zipCode >= 60000
            && $zipCode <= 76999
        ) {
            $distribution = 'Rhein - Main - Neckar';
        } else if (in_array($zipCode, array(80687,80997,80687,80689,81379,81377,82110,80335,82110,82152,80639,80333,80802,80639,80797,80798,80807,80686,80799,80804,80993,80992,80799,80805,80809,80799,80809,80935,80801,80798,80939,82319,80992,80997,81241,81377,81241,81379,82061,81371,81479,81477,81379,81479,81541,81539,81476,81541,81541,81669,81543,81673,81541,81671,81827,81547,81927,82131,81541,82211,81675,81667,80335,81679,80339,80339,81679,80634,80636,80636,80637,80637,81735,81825,80469,82223,82166,80339,82319,82140,82327,80469,82327,80337,81825,82386,80538,80469,82402,82467,82487,82067,83209,82211,80538,83471,82377,83471,82467,83646,83022,83661,82054,83703,82319,83707,82229,85551,83727,83735,82362,84416,82327,85221,83022,85354,82418,85435,82515,82041,82515,83435,85635,85521,82008,82031,85521,83080,82054,83607,85579,83620,85604,83620,85614,85540,82008,83700,85774,85221,85640,86316,86695,81245,86316,81677,80687,85540,81927,85540,82299,83098,85399,83115,85586,85598,86343,86316,86343,86456,85737,86152,85630,86154,86157,86157,86159,86157,86159,86161,80992,))
        ) {
            $distribution = 'München/Oberbayern';
        } else {
            $logger->log($this->_companyId . ': unknown distribution for zipcode ' . $zipCode, Zend_Log::ERR);
        }

        // distributions to assign pdfs
        $foundDistribution = false;
        $regionZipcodes = $this->_getRegionZip();
        foreach ($regionZipcodes as $regionCode => $regionZip) {
            if (in_array($zipCode, $regionZip)) {
                $distribution .= ',' . $regionCode;
                $foundDistribution = true;
                break;
            }
        }

        if (!$foundDistribution){
            $logger->log($this->_companyId . ': no pdf-distribution found for zipcode ' . $zipCode, Zend_Log::ERR);
        }

        return $distribution;
    }
}