<?php

/* 
 * Prospekt Crawler für Manor CH (ID: 72138)
 */

class Crawler_Company_PostCh_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aBrochure = $sFtp->listFiles('.', '#\.pdf#');
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTranslation = new Marktjagd_Service_Text_Translation();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($aBrochure as $brochure) {
            $filePath = $sFtp->downloadFtpToDir($brochure, $localPath);
            $aBrochureParams = explode(';', str_replace('.pdf', '', $brochure));
            $language = $aBrochureParams[5];
            $strStoreNumbers = '';

            /* @var $eStore Marktjagd_Entity_Api_Store */
            foreach ($cStores as $eStore) {
                if (preg_match('#^' . $language . '$#i', $sTranslation->findLanguageCodeForZipcode($eStore->getZipcode()))) {
                    if (strlen($strStoreNumbers)) {
                        $strStoreNumbers .= ',';
                    }
                    $strStoreNumbers .= $eStore->getStoreNumber();
                }
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle($aBrochureParams[0]);
            $eBrochure->setStart(date('d.m.Y', strtotime($aBrochureParams[1])));
            $eBrochure->setEnd(date('d.m.Y', strtotime($aBrochureParams[2])));
            $eBrochure->setVisibleStart(date('d.m.Y', strtotime($aBrochureParams[3])));
            $eBrochure->setVisibleEnd(date('d.m.Y', strtotime($aBrochureParams[4])));
            $eBrochure->setStoreNumber($strStoreNumbers);
            $eBrochure->setLanguageCode($language);
            $eBrochure->setUrl($sFtp->generatePublicFtpUrl($filePath));
            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
