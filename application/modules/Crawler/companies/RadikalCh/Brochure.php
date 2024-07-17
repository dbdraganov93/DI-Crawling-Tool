<?php

/* 
 * Prospekt Crawler für Radikal Liquidationen CH (ID: 72160)
 */

class Crawler_Company_RadikalCh_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $aLanguages = array(
            'de' => array('title' => 'Monatsangebote'),
            'fr' => array('title' => 'Offres de mois')
        );
        
        $baseUrl = 'https://radikal-liquidationen.ch/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        
        $cStores = $sApi->findStoresByCompany($companyId);
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguages as $singleLanguage => $infoFields) {
            $sPage->open($baseUrl . $singleLanguage);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#href="([^"]+?(\d)\.pdf)"#';
            if (!preg_match_all($pattern, $page, $brochurePageMatches)) {
                $this->_logger->err($companyId . ': no brochure pages for ' . $singleLanguage);
                continue;
            }
            
            $aPdfPages = array_combine($brochurePageMatches[2], $brochurePageMatches[1]);
            
            $localPdfPath = $sHttp->generateLocalDownloadFolder($companyId);
            foreach ($aPdfPages as $pageNo => $singlePage) {
                $aPdfPages[$pageNo] = $sHttp->getRemoteFile($baseUrl . $singlePage, $localPdfPath);
            }
            
            ksort($aPdfPages);
            
            $localBrochurePath = $sPdf->merge($aPdfPages, $localPdfPath);
            
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            
            $eBrochure->setUrl($sHttp->generatePublicHttpUrl($localBrochurePath))
                    ->setTitle($infoFields['title'])
                    ->setStart(date('d.m.Y' , strtotime('last tuesday of last month')))
                    ->setVariety('leaflet')
                    ->setLanguageCode($singleLanguage)
                    ->setBrochureNumber(date('m', strtotime('today')) . '_' . $singleLanguage);
            
            $aZipcode = $sTranslation->findZipcodesForLanguageCode($singleLanguage);

            $sStoreNumbers = '';
            /* @var $eStore Marktjagd_Entity_Api_Store */
            foreach ($cStores->getElements() as $eStore) {
                if (in_array(trim($eStore->getZipcode()), $aZipcode)) {
                    if (strlen($sStoreNumbers)) {
                        $sStoreNumbers .= ', ';
                    }

                    $sStoreNumbers .= $eStore->getStoreNumber();
                }
            }

            $eBrochure->setStoreNumber($sStoreNumbers);
            
            $cBrochures->addElement($eBrochure);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}