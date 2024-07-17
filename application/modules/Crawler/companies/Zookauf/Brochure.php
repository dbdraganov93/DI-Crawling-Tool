<?php

/*
 * Brochure Crawler für Zookauf (ID: 29000)
 */

class Crawler_Company_Zookauf_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $sArchive = new Marktjagd_Service_Input_Archive();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $aBrochures = array(
            '29000' => '#zookauf#i',
            '29088' => '#kiebitz#i',
            '72057' => '#heimtierpartner#i',
            '72064' => '#raiffeisen#i',
            '72065' => '#pet\s*power#i'
        );

        $cApiStores = $sApi->findStoresByCompany($companyId)->getElements();

        $sFtp->connect('29000');
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        foreach ($sFtp->listFiles('.', '#\.zip$#') as $singleFile) {
            $localZipFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
            $sArchive->unzip($localZipFile, $localPath);

        }
        foreach (scandir($localPath) as $singleLocalFile) {
            if (preg_match('#([^\.]+?)\.pdf$#', $singleLocalFile, $brochureNameMatch)) {
                $aLocalBrochures[$brochureNameMatch[1]] = $localPath . $singleLocalFile;
            }
        }

        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#Teilnehmer#', $singleFile)) {
                $aData = $sExcel->readFile($localPath . '/' . $singleFile, TRUE)->getElement(0)->getData();
                break;
            }
        }
        $aInfos = array();
        foreach ($aData as $singleStore) {
            if (!preg_match($aBrochures[$companyId], $singleStore['Vertriebslinie'])) {
                continue;
            }
            $strStoreNumber = '';
            foreach ($cApiStores as $eApiStore) {
                if (preg_match('#' . $eApiStore->getZipcode() . '#', str_pad($singleStore['PLZ'], 5, '0', STR_PAD_LEFT))) {
                    $strStoreNumber = $eApiStore->getStoreNumber();
                }
            }

            if (!strlen($strStoreNumber)) {
                throw new Exception($companyId . ': store missing - ' . $singleStore['PLZ'] . ' ' . $singleStore['Ort'] . ', ' . $singleStore['Strasse']);
            }

            foreach (array_keys($aLocalBrochures) as $singleBrochureTitle) {
                if (preg_match('#' . $singleBrochureTitle . '#', $singleStore['Heimtierjournal'])) {
                    if (!array_key_exists(preg_replace('#\s#', '%20', $aLocalBrochures[$singleBrochureTitle]), $aInfos)) {
                        $aValidity = preg_split('#\s*-\s*#', $singleStore['Zeitraum_Heimtierjournal']);
                        $strTitle = 'Heimtierjournal';
                        $aInfos[preg_replace('#\s#', '%20', $aLocalBrochures[$singleBrochureTitle])] = array(
                            'title' => $strTitle,
                            'validStart' => $aValidity[0],
                            'validEnd' => $aValidity[1]
                        );
                    }
                    $aInfos[$aLocalBrochures[$singleBrochureTitle]]['stores'][] = $strStoreNumber;
                }

                if (strlen($singleStore['Handzettel']) && preg_match('#' . $singleStore['Handzettel'] . '#', $singleBrochureTitle)) {
                    if (!array_key_exists($aLocalBrochures[$singleBrochureTitle], $aInfos)) {
                        $aValidity = preg_split('#\s*-\s*#', $singleStore['Zeitraum_Handzettel']);
                        $strTitle = 'Handzettel';
                        $aInfos[$aLocalBrochures[$singleBrochureTitle]] = array(
                            'title' => $strTitle,
                            'validStart' => $aValidity[0],
                            'validEnd' => $aValidity[1]
                        );
                    }
                    $aInfos[$aLocalBrochures[$singleBrochureTitle]]['stores'][] = $strStoreNumber;
                }

                if (strlen($singleStore['Trixie Kampagne']) && preg_match('#Trixie#', $singleBrochureTitle)) {
                    if (!array_key_exists($aLocalBrochures[$singleBrochureTitle], $aInfos)) {
                        $aValidity = preg_split('#\s*-\s*#', $singleStore['Zeitraum_Themenkatalog_Katze']);
                        $strTitle = 'Trixie Kampagne';
                        $aInfos[$aLocalBrochures[$singleBrochureTitle]] = array(
                            'title' => $strTitle,
                            'validStart' => $aValidity[0],
                            'validEnd' => $aValidity[1]
                        );
                    }
                    $aInfos[$aLocalBrochures[$singleBrochureTitle]]['stores'][] = $strStoreNumber;
                }
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aInfos as $path => $aBrochureInfos) {
                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle($aBrochureInfos['title'])
                    ->setStart($aBrochureInfos['validStart'])
                    ->setEnd($aBrochureInfos['validEnd'])
                    ->setVisibleStart($eBrochure->getStart())
                    ->setUrl($path)
                    ->setStoreNumber(implode(',', $aBrochureInfos['stores']))
                    ->setVariety('leaflet');

                $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
