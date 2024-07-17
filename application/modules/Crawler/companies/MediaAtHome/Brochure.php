<?php

/**
 * Prospekt Crawler für Media @ Home (ID: 22329)
 */
class Crawler_Company_MediaAtHome_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $aUnvStores = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $aForeignStoreNo = array(
            '#215417#'
        );
        
        $aOwnStoreNo = array(
            '1100668'
        );
        
        $aUnvStores = $aUnvStores->findStoresByCompany($companyId);
        $this->_aUnvStores = $aUnvStores->getElements();

        $sFtp->connect('86');
        $localDirectory = $sFtp->generateLocalDownloadFolder($companyId);
        $aFolders = $sFtp->listFiles();
        
        foreach ($aFolders as $singleFolder) {
            $pattern = '#mediaathome_(\d{2,})kw(\d{2})#';
            if (preg_match($pattern, $singleFolder, $folderMatch)) {
                $strFolder = $folderMatch[0];
                
                $strYear = $folderMatch[1];
                if (strlen($strYear) == 2) {
                    $strYear = '20' . $strYear;
                }                
                
                $strWeek = str_pad($folderMatch[2], 2, '0', STR_PAD_LEFT);
            }
        }

        if (!strlen($strFolder)) {
            $this->_response->setIsImport(false);
            $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
            return $this->_response;
        }
        
        $sFtp->changedir($strFolder);
        $aFiles = $sFtp->listFiles();        
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $errCount = 0;
        
        foreach ($aFiles as $singleFile) {
            $pattern = '#(mh.+-([0-9]+))\.pdf$#i';
            if (!preg_match($pattern, $singleFile, $storeNumberMatch)) {
                //$this->_logger->err($companyId . ': unable to get store number from pdf: ' . $singleFile);
                $errCount++;
                continue;
            }                       
            
            $localFileName = $sFtp->downloadFtpToDir($singleFile, $localDirectory);
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Media@Home Angebot')
                        ->setStart($sTimes->findDateForWeekday($strYear, $strWeek - 1, 'Sa'))
                        ->setEnd($sTimes->findDateForWeekday($strYear, $strWeek + 1, 'Sa'))
                        ->setVisibleStart($eBrochure->getStart())
                        ->setStoreNumber(preg_replace($aForeignStoreNo, $aOwnStoreNo, $storeNumberMatch[2]))
                        ->setUrl(preg_replace('#(.+?)(/files.+?)#', 'https://di-gui.marktjagd.de$2', $localFileName))
                        ->setVariety('leaflet')
                        ->setBrochureNumber($storeNumberMatch[1]);
                $cBrochures->addElement($eBrochure);
        }
        
        if (!$errCount) {
            $sFtp->move('../' . $strFolder, '../0 Archiv/' . $strFolder);
        }
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}