<?php
/**
 * Brochure Crawler für Jysk CH (ID: 72181)
 */

class Crawler_Company_JyskCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $aInfos = array(
            'D' => array(
                'languageCode' => 'DE',
                'title' => 'Der grösste Sale!',
                'stores' => 'id:1089509,id:1089508,id:1089535,id:1089537,id:1089524,id:1089526,id:1089534'
                ),
            'F' => array(
                'languageCode' => 'FR',
                'title' => 'Énorme Sale!',
                'stores' => 'id:1089527,id:1089505,id:1089509'
            ),
            'I' => array(
                'languageCode' => 'IT',
                'title' => 'Grandissimo Sale!',
                'stores' => 'id:1089516'
            ),
        );

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aLocalBrochuresFiles = array();
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#CH([a-z])[a-z]_([^-]+?)-(.+?\d{4}).*\.pdf$#', $singleFile, $brochureInfoMatch)) {
                $aLocalBrochuresFiles[strtoupper($brochureInfoMatch[1])] = array(
                    'validStart' => $brochureInfoMatch[2],
                    'validEnd' => $brochureInfoMatch[3],
                    'path' => $sFtp->downloadFtpToDir($singleFile, $localPath)
                );
            }
        }

        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLocalBrochuresFiles as $languageCode => $aSingleBrochureInfos) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($aInfos[$languageCode]['title'])
                ->setUrl($sFtp->generatePublicFtpUrl($aSingleBrochureInfos['path']))
                ->setVisibleStart($aSingleBrochureInfos['validStart'])
                ->setEnd($aSingleBrochureInfos['validEnd'])
                ->setStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' + 1 day')))
                ->setVariety('leaflet')
                ->setStoreNumber($aInfos[$languageCode]['stores'])
                ->setLanguageCode($aInfos[$languageCode]['languageCode'])
                ->setBrochureNumber(date('W', strtotime($eBrochure->getStart())) . '_' . date('Y', strtotime($eBrochure->getStart())) . '_' . $aInfos[$languageCode]['languageCode']);

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);

    }
}
