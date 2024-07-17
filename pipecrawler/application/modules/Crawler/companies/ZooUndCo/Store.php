<?php

/**
 * Storecrawler für Zoo & Co. (ID: 338)
 */
class Crawler_Company_ZooUndCo_Store extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();

        $apiStores = $sApi->findStoresByCompany($companyId);

        $aAssignedDists = array();
        foreach ($apiStores->getElements() as $eStore) {
            $aAssignedDists[$eStore->getStoreNumber()] = $eStore->getDistribution();
        }

        ksort($aAssignedDists);

        $url = 'http://zuc:marktdatenexport@www.zooundco24.de/fileadmin/zooundco24.de/marktdaten-export/marktjagd/marktdaten-export_marktjagd.csv';
        $patternBrochureAssignment = '#([^\.]+?)\.xlsx?#';

        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);
        $downloadPathFile = $sHttp->getRemoteFile($url, $downloadPath);

        $sFtp->connect($companyId);
        $assignmentFile = $sFtp->listFiles('.', $patternBrochureAssignment);
        $localAssignmentFile = $sFtp->downloadFtpToDir($assignmentFile[0], $downloadPath);

        if (preg_match($patternBrochureAssignment, $assignmentFile[0], $distMatch)) {
            $strDist = $distMatch[1];
        }

        $aExcelAssignmentData = $sExcel->readFile($localAssignmentFile, TRUE)->getElement(0)->getData();
        $aStoreNumbers = array();
        foreach ($aExcelAssignmentData as $singleData) {
            $aStoreNumbers[$singleData['PLZ']] = $singleData['Saga-Nr.'];
        }


        $aStores = $sExcel->readFile($downloadPathFile, TRUE)->getElement(0)->getData();
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStores as $singleStore) {
            if (array_key_exists('Country Code', $singleStore) && $singleStore['Country Code'] != 'DE') {
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $aDistUnv = array();
            if (strlen($aAssignedDists[$singleStore['Store Code']])) {
                $aDistUnv = preg_split('#\s*,\s*#', $aAssignedDists[$singleStore['Store Code']]);
            }

            if (array_key_exists($singleStore['Postal Code'], $aStoreNumbers)) {
                $aDistUnv[] = 'Werbebeilage_' . $strDist;
                unset($aStoreNumbers[$singleStore['Postal Code']]);
            }

            $aDistUnv = array_unique($aDistUnv);

            $eStore->setDistribution(implode(',', $aDistUnv));

            // Sortiment als Abteilungen aufnehmen
            if (preg_match('#Produktsortiment:\s*(.+?)\s*([\|]|$)#', $singleStore['Beschreibungstext für Landingpage'], $match)) {
                if ($match[1] != 'N/A') {
                    $eStore->setSection($match[1]);
                }
            }

            // Dienstleistungen als Service aufnehmen
            if (preg_match('#Dienstleistungen:\s*(.+?)\s*([\|]|$)#', $singleStore['Beschreibungstext für Landingpage'], $match)) {
                if ($match[1] != 'N/A') {
                    $eStore->setService($match[1]);
                }
            }

            // Lebendtiere in den Beschreibungstext
            if (preg_match('#Lebendtiere:\s*(.+?)\s*([\|]|$)#', $singleStore['Beschreibungstext für Landingpage'], $match)) {
                if ($match[1] != 'N/A') {
                    $eStore->setText($eStore->getText() . "<br /><br />Lebendtiere: " . $match[1]);
                }
            }

            // Marken in den Beschreibungstext
            if (preg_match('#Marken:\s*(.+?)\s*([\|]|$)#', $singleStore['Beschreibungstext für Landingpage'], $match)) {
                if ($match[1] != 'N/A') {
                    $eStore->setText(
                        $eStore->getText() . "<br /><br />Marken: " . implode(', ', explode(',', $match[1]))
                    );
                }
            }

            if (strlen($eStore->getText()) > 1000) {
                $eStore->setText(substr($eStore->getText(), 0, 950) . '...');
            }

            $eStore->setStoreNumber($singleStore['Store Code'])
                ->setStreetAndStreetNumber($singleStore['Address Line 1'])
                ->setCity($singleStore['City'])
                ->setZipcode($singleStore['Postal Code'])
                ->setPhoneNormalized($singleStore['Main Phone'])
                ->setFaxNormalized($singleStore['Fax'])
                ->setWebsite($singleStore['Home Page'])
                ->setEmail($singleStore['Email'])
                ->setStoreHoursNormalized($sTimes->convertGoogleOpenings($singleStore['Hours']));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
