<?php

/* 
 * Prospekt Crawler für RL Fundgrube (ID: 69972)
 */

class Crawler_Company_RLFundgrube_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.rl-fundgrube.de/';
        $searchUrl = $baseUrl . 'aktuelles/werbeflyer.php';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<h[^>]*>\s*Aktueller\s*Werbeflyer\s*</h[^>]*>(.+?)</div>\s*</div#s';
        if (!preg_match($pattern, $page, $brochureListMatch)) {
            throw new Exception($companyId . ': unable to get brochure list.');
        }
        
        $pattern = '#<[^>]*href="([^"]+?(\d+)[^"]+?\.pdf)"#';
        if (!preg_match_all($pattern, $brochureListMatch[1], $brochurePageMatches)) {
            throw new Exception($companyId . ': unable to get any brochure pages from list.');
        }
        
        $aBrochurePages = array_combine($brochurePageMatches[2], $brochurePageMatches[1]);
        
        ksort($aBrochurePages);
        
        $sHttp = new Marktjagd_Service_Transfer_Http();
        
        $localFolder = $sHttp->generateLocalDownloadFolder($companyId);
        
        foreach ($aBrochurePages as $siteId => $remoteFilePath) {
            $aBrochurePages[$siteId] = $sHttp->getRemoteFile($remoteFilePath, $localFolder);
        }
                
        $sPdf = new Marktjagd_Service_Output_Pdf();
        
        $strCompletePdf = $sPdf->merge($aBrochurePages, $localFolder);
        
        $pattern = '#Unsere\s*Werbung,[^\d]+?(\d{2}\.)(\d{2}\.)(\d{2,4})\.#';
        if (!preg_match($pattern, $brochureListMatch[1], $validStartMatch)) {
            throw new Exception($companyId . ': unable to get brochure validity start.');
        }
        
        $strStart = $validStartMatch[1] . $validStartMatch[2];
        if (strlen($validStartMatch[3]) == 2) {
            $strStart .= '20' . $validStartMatch[3];
        } else {
            $strStart .= $validStartMatch[3];
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        
        $eBrochure->setTitle('Schnäppchen & Sonderposten')
                ->setStart($strStart)
                ->setVariety('leaflet')
                ->setUrl($sHttp->generatePublicHttpUrl($strCompletePdf))
                ->setBrochureNumber(preg_replace('#\.#', '', $eBrochure->getStart()));
                
        $cBrochures->addElement($eBrochure);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}