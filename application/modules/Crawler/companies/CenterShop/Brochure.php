<?php

/*
 * Prospekt Crawler für Center Shop (ID: 69971)
 */

class Crawler_Company_CenterShop_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_Ftp();
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $aApiStores = $sApi->findAllStoresForCompany($companyId);
        $aApiStoresToAssign = [];
        foreach ($aApiStores as $singleApiStore) {
            $aApiStoresToAssign[$singleApiStore['number']] = $singleApiStore['number'];
        }

        $aFtpConfig = array(
            'hostname' => 'ftpcs.centershop.de',
            'username' => 'grafik',
            'password' => '!rtgP7Ne.%s2',
            'port' => '21'
        );

        $week = 'next';
        $weekNo = date('W', strtotime($week . ' week'));

        $localPath = $sFtp->generateLocalDownloadFolder($companyId);
        $sFtp->connect($aFtpConfig);
        $this->_logger->info('connectiong to : ftpcs.centershop.de' );

        foreach ($sFtp->listFiles('./_OFFERISTA') as $singleFile) {
            if (preg_match('#KW' . $weekNo . '-Centershop.zip$#', $singleFile)) {
                $this->_logger->info('File ' . $singleFile . ' was found! Downloading...' );
                $localArchive = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }
        }
        $sFtp->close();
        $this->_logger->info('Download successful!' );

        if (!$sArchive->unzip($localArchive, $localPath)) {
            throw new Exception($companyId . ': unable to extract archive ' . $localArchive);
        }

        $strAssignmentFile = '';
        // This localpath inside .zip seems to have irregularities each week
        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#([^\.]+?)\.pdf$#', $singleFile, $fileNameMatch)) {
                $aBrochuresToAssign[$fileNameMatch[1]] = $localPath . $singleFile;
                continue;
            }

            if (preg_match('#\.xlsx?$#', $singleFile)) {
                $strAssignmentFile = $localPath . $singleFile;
            }
        }
        $aData = $sPss->readFile($strAssignmentFile)->getElement(0)->getData();

        $aHeader = [];
        for ($i = 0; $i < count($aData); $i++) {
            if (!count($aHeader) && !strlen($aData[$i][4])) {
                continue;
            }
            if (!count($aHeader)) {
                $aHeader = $aData[$i];
                continue;
            }

            // Assign stores to especific brochures
            if (preg_match('#OUT#', $aData[$i][2])) {
                $aStoresToAssign['OUT'][] = $aApiStoresToAssign[$aData[$i][2]];
            } elseif (preg_match('#WSB#', $aData[$i][2])) {
                $aStoresToAssign['WSB'][] = $aApiStoresToAssign[$aData[$i][2]];
            }
            // search excel column for a 1 and create $aApiStoresToAssign array with storeNumbers
            if ($aData[$i][4] == 1) {
                $aStoresToAssign[$aHeader[4]][] = $aApiStoresToAssign[trim($aData[$i][2])];
            } elseif ($aData[$i][5] == 1) {
                $aStoresToAssign[$aHeader[5]][] = $aApiStoresToAssign[trim($aData[$i][2])];
            } elseif ($aData[$i][6] == 1) {
                $aStoresToAssign[$aHeader[6]][] = $aApiStoresToAssign[trim($aData[$i][2])];
            }
        }
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochuresToAssign as $brochureName => $brochurePath) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $strStoreNumbers = '';
            // Assign stores to especific brochures
            if (preg_match('#OUT#', $brochureName)) {
                $strStoreNumbers = implode(',', $aStoresToAssign['OUT']);
            } elseif (preg_match('#WSB#', $brochureName)) {
                $strStoreNumbers = implode(',', $aStoresToAssign['WSB']);
            } else {
                foreach ($aStoresToAssign as $brochureIdentifier => $aStoreNumbers) {
                    // searches for a file with names like: 16-Seiter, _normal, 16-?seiter, 16er or even KW30_CS
                    if (preg_match(
                        '#' . $brochureIdentifier . '|_normal|16-?seiter_CS|16er|KW' . $weekNo . '_CS#',
                        $brochureName
                    )) {
                        $strStoreNumbers = implode(',', $aStoreNumbers);
                        break;
                    } elseif (
                        preg_match('#' . $brochureIdentifier . '|20-?seiter_CS|20er#', $brochureName) &&
                        preg_match('#20-Seiter#', $brochureIdentifier)
                    ) {
                        $strStoreNumbers = implode(',', $aStoreNumbers);
                        break;
                    }
                }
            }

            if(empty($strStoreNumbers)) {
                throw new Exception(
                    'The stores to assign is empty, probably Centershop changed the PDF file names or .xls file'
                );
            }

            $eBrochure->setUrl($brochurePath)
                ->setTitle('Wochenangebote')
                ->setBrochureNumber($brochureName . '_' . date('Y', strtotime($week . ' week')))
                ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . '-1 day')))
                ->setStoreNumber($strStoreNumbers)
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
