<?php

/* 
 * Prospekt Crawler für Vögele Shoes CH (ID: 72216)
 */

class Crawler_Company_VoegeleShoesCh_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://ch-de.voegele-shoes.com';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*neue\s*Prospekt#';
        if (!preg_match($pattern, $page, $brochurePathNameMatch)) {
            throw new Exception($companyId . ': unable to get brochure path name.');
        }

        $sPage->open($brochurePathNameMatch[1]);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<meta[^>]*name="description"[^>]*content="([^"]+?)"#';
        if (!preg_match($pattern, $page, $brochureInfoMatch)) {
            throw new Exception($companyId . ': unable to get brochure info.');
        }

        $pattern = '#Length:\s*(\d+)#';
        if (!preg_match($pattern, $brochureInfoMatch[1], $brochurePageMatch)) {
            throw new Exception($companyId . ': unable to get brochure page info.');
        }

        $pattern = '#<link[^>]*rel="image_src"[^>]*href="([^"]+?page_\d+[^"]+?)"#';
        if (!preg_match($pattern, $page, $brochureImageMatch)) {
            throw new Exception($companyId . ': unable to get brochure image url.');
        }

        $sHttp = new Marktjagd_Service_Transfer_Http();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        for ($i = 1; $i <= $brochurePageMatch[1]; $i++) {
            $sHttp->getRemoteFile(preg_replace('#page_\d+#', 'page_' . $i, $brochureImageMatch[1]), $localPath);
        }

        $sPdf = new Marktjagd_Service_Output_Pdf();
        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#_(\d+)\.jpg#', $singleFile, $siteMatch)) {
                $sPdf->createPdf($localPath . $singleFile);
            }
        }

        $aPdfSites = array();
        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#_(\d+)\.pdf#', $singleFile, $siteMatch)) {
                $aPdfSites[$siteMatch[1]] = $localPath . $singleFile;
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        
        $eBrochure->setUrl($sHttp->generatePublicHttpUrl($sPdf->merge($aPdfSites, $localPath)))
                ->setTitle('Monatsangebote')
                ->setVariety('leaflet')
                ->setTags('Damen, Herren, Pump, Kinder, Sneaker, nike, Puma');
        
        $cBrochures->addElement($eBrochure);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}