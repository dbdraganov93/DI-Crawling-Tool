<?php

/*
 * Prospekt Crawler für IKEA AT (ID: 72283)
 */

class Crawler_Company_IkeaAt_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ikea.com/';
        $searchUrl = $baseUrl . 'ms/de_AT/customer-service/about-shopping/catalogue-and-brochures/index.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*target="_blank">Online ansehen#';
        if (!preg_match_all($pattern, $page, $brochureUrlMatches)) {
            throw new Exception($companyId . ': no brochures available.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureUrlMatches[1] as $singleBrochureUrl) {
            $siteNo = 1;
            $aImages = array();
            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            while (TRUE) {
                $sPage->open($singleBrochureUrl . 'pages/' . $siteNo);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<meta[^>]*property="og:image"[^>]*content="([^"]+?)"#';
                if (preg_match($pattern, $page, $imagePathMatch)) {
                    $aImages[$siteNo++] = $imagePathMatch[1];
                } else {
                    break;
                }

                $pattern = '#<meta[^>]*property="og:title"[^>]*content="([^"]+?)"#';
                if (!preg_match($pattern, $page, $titleMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure title: ' . $singleBrochureUrl);
                }
            }

            $aPdfs = array();
            foreach ($aImages as $pageNo => $singleImage) {
                if (!strlen($singleImage)) {
                    continue;
                }
                mkdir($localPath . $pageNo);
                $pagePath = $localPath . $pageNo . '/';
                $localImagePath = $sHttp->getRemoteFile(preg_replace('#M\.jpg#', '3XL.jpg', $singleImage), $pagePath);
                $sPdf->createPdf($localImagePath);
                foreach (scandir($pagePath) as $singleFile) {
                    if (preg_match('#\.pdf$#', $singleFile)) {
                        $aPdfs[$pageNo] = $pagePath . $singleFile;
                        break;
                    }
                }
            }
            $mergedPdfFilePath = $sPdf->merge($aPdfs, $localPath);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            
            $eBrochure->setUrl($sHttp->generatePublicHttpUrl($mergedPdfFilePath))
                    ->setTitle(preg_replace('#IKEA\s*#', '', $titleMatch[1]))
                    ->setVariety('leaflet')
                    ->setTags('Wohnzimmer, Esszimmer, Schlafen, Sessel, Bett, Accessoires, Deko');
            
            $cBrochures->addElement($eBrochure);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
