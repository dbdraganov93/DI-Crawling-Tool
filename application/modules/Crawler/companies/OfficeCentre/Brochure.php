<?php
/**
 * Brochure Crawler für OfficeCentre (ID: 317)
 */

class Crawler_Company_OfficeCentre_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $aCounties = array();
        foreach ($cStores as $eStore) {
            $aCounties[$sDbGeo->findRegionByZipCode($eStore->getZipcode())][] = $eStore->getStoreNumber();
        }

        $aVersions = array(
            'V1' => array(
                'counties' => array(
                    'Bremen',
                    'Niedersachsen',
                    'Hessen',
                    'Rheinland-Pfalz'
                ),
                'Beilage_Start' => '28.07.2018',
                'Beilage_End' => '18.08.2018'
            ),
            'V2' => array(
                'counties' => array(
                    'Hamburg'
                ),
                'Beilage_Start' => '04.08.2018',
                'Beilage_End' => '25.08.2018'
            ),
            'V3' => array(
                'counties' => array(
                    'Schleswig-Holstein'
                ),
                'Beilage_Start' => '11.08.2018',
                'Beilage_End' => '01.09.2018'
            ),
            'V4' => array(
                'counties' => array(
                    'Nordrhein-Westfalen'
                ),
                'Beilage_Start' => '18.08.2018',
                'Beilage_End' => '08.09.2018'
            ),
            'V5' => array(
                'counties' => array(
                    'Bayern',
                    'Baden-Württemberg'
                ),
                'Beilage_Start' => '01.09.2018',
                'Beilage_End' => '22.09.2018'
            )
        );

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $versionMax = array();
        foreach ($sFtp->listFiles('./BTS-Kampagnen') as $singleFile) {
            if (preg_match('#([^\s_]+)_page\d+_?V(\d)\.pdf#', $singleFile, $infoMatch)) {
                if (!array_key_exists($infoMatch[1], $versionMax)) {
                    $versionMax[$infoMatch[1]] = 0;
                }
                if ($versionMax[$infoMatch[1]] < $infoMatch[2]) {
                    $versionMax[$infoMatch[1]] = $infoMatch[2];
                }
            }
        }

        $aBrochures = array();
        foreach ($sFtp->listFiles('./BTS-Kampagnen') as $singleFile) {
            $pattern = '#([^\s_]+)_page(\d+)_?(V\d)?\.pdf#';
            if (preg_match($pattern, $singleFile, $infoMatch)) {
                $localFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                if (!strlen($infoMatch[3])) {
                    for ($i = 1; $i <= $versionMax[$infoMatch[1]]; $i++) {
                        $aBrochures[$infoMatch[1]]['V' . $i][(int)$infoMatch[2]] = $localFile;
                    }
                } else {
                    $aBrochures[$infoMatch[1]][$infoMatch[3]][(int)$infoMatch[2]] = $localFile;
                }
            }
        }

        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochures as $variant => $aBrochureVersions) {
            foreach ($aBrochureVersions as $versionNumber => $aPdfs) {
                sort($aPdfs);

                $strStoreNumbers = '';
                foreach ($aVersions[$versionNumber]['counties'] as $singleCounty) {
                    if (strlen($strStoreNumbers)) {
                        $strStoreNumbers .= ',';
                    }

                    $strStoreNumbers .= implode(',', $aCounties[$singleCounty]);
                }

                $brochurePath = $sPdf->merge($aPdfs, $localPath);
                $brochurePath = $sPdf->trim($brochurePath);

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setUrl($sFtp->generatePublicFtpUrl($brochurePath))
                    ->setStoreNumber($strStoreNumbers)
                    ->setStart($aVersions[$versionNumber][$variant . '_Start'])
                    ->setEnd($aVersions[$versionNumber][$variant . '_End'])
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet')
                    ->setBrochureNumber($variant . '_' . $versionNumber);

                $cBrochures->addElement($eBrochure);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}