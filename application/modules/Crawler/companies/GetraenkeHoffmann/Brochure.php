<?php

/*
 * Brochure Crawler for Getränke Hoffmann (ID: 29135)
 */

class Crawler_Company_GetraenkeHoffmann_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $week = 'next';

        $aStores = $sApi->findAllStoresForCompany($companyId);
        $aStoreNumbers = [];
        foreach ($aStores as $singleStore) {
            $aStoreNumbers[] = $singleStore['zipcode'];
        }
        $localPath = $sFtp->connect(29135, TRUE);

        $brochures = [];
        $assignmentFile = '';

        $remoteFilePath = './Handzettel/';
        foreach ($sFtp->listFiles($remoteFilePath) as $singleFolder) {
            if (preg_match('#KW\s*' . date('W', strtotime($week . ' week')) . '-' . date('Y', strtotime($week . ' week')) . '#', $singleFolder)) {
                $remoteFilePath = $singleFolder . '/';
                break;
            }
        }

        foreach ($sFtp->listFiles($remoteFilePath) as $singleRemoteFile) {
            if (preg_match('#\.xlsx$#', $singleRemoteFile)) {
                $assignmentFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                continue;
            }

        }

        $aData = $sPss->readFile($assignmentFile, TRUE)->getElement(0)->getData();

        foreach ($aData as $singleRow) {
            if (!in_array($singleRow['PLZ'], $aStoreNumbers) || !$singleRow['PDF-Name "Standard Version"']) {
                continue;
            }

            if (preg_match('#\d{4,5}#', $singleRow['plz'])) {
                $brochures[$singleRow['PDF-Name "Standard Version"']]['postalCode'][] = str_pad(trim($singleRow['plz']), 5, '0', STR_PAD_LEFT);
            } else {
                $brochures[$singleRow['PDF-Name "Standard Version"']]['postalCode'][] = str_pad(trim($singleRow['PLZ']), 5, '0', STR_PAD_LEFT);
            }
        }

        foreach ($sFtp->listFiles($remoteFilePath) as $singleRemoteFile) {
            if (preg_match('#([^_-]+)-([\d.]+)[^.]*\.pdf$#', $singleRemoteFile, $match)
                && array_key_exists(basename($singleRemoteFile), $brochures)) {
                $brochures[basename($singleRemoteFile)]['path'] = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $brochures[basename($singleRemoteFile)]['validityStart'] = preg_replace('#\.(\d{2})$#', '.20$1', $match[1]);
                $brochures[basename($singleRemoteFile)]['validityEnd'] = preg_replace('#\.(\d{2})$#', '.20$1', $match[2]);
            }
        }
        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochures as $singleBrochure) {
            $eBrochure = $this->_createBrochure($singleBrochure);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }

    /**
     * @param $singleBrochure
     * @return Marktjagd_Entity_Api_Brochure
     */
    public
    function _createBrochure($singleBrochure): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Getränkeangebote')
            ->setBrochureNumber(pathinfo($singleBrochure['path'], PATHINFO_FILENAME))
            ->setUrl($singleBrochure['path'])
            ->setStart($singleBrochure['validityStart'])
            ->setEnd($singleBrochure['validityEnd'])
            ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . '- 1 day')))
            ->setZipCode(implode(',', $singleBrochure['postalCode']));

        return $eBrochure;
    }
}
