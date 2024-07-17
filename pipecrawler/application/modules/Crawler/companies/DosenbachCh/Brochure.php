<?php

/*
 * Prospekt Crawler für Dosenbach CH (ID: 72182)
 */

class Crawler_Company_DosenbachCh_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.dosenbach.ch/';
        $searchUrl = $baseUrl . 'CH/de/shop/marketing/prospekt.cat';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*<img[^>]*src="[^"]+?(Prospekt_[^\_]+?)_#';
        if (!preg_match($pattern, $page, $brochureUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure url.');
        }

        $ch = curl_init($brochureUrlMatch[1]);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);

        $strRedirectedUrl = urldecode(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));

        $pattern = '#([^\/\s]+)\s*$#';
        if (!preg_match($pattern, $strRedirectedUrl, $brochurePathMatch)) {
            throw new Exception($companyId . ': unable to get brochure path.');
        }

        $siteNo = 0;
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        while (++$siteNo > 0) {
            $imageUrl = 'http://deichmann.scene7.com/is/image/deichmann/' . $brochurePathMatch[1] . '-' . $siteNo . '?fit=constrain,1&wid=1200&hei=850&fmt=jpg';
            if (!$sPage->checkUrlReachability($imageUrl)) {
                break;
            }
            $sHttp->getRemoteFile($imageUrl, $localPath);
        }
        
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $aImages = array();
        $pattern = '#-(\d+)$#';
        foreach (scandir($localPath) as $singleImageFile) {
            if (!preg_match($pattern, $singleImageFile, $pageMatch)) {
                continue;
            }
            exec('mv ' . $localPath . $singleImageFile . ' ' . $localPath . $singleImageFile . '.jpg');
            $aImages[$pageMatch[1]] = $localPath . $singleImageFile . '.jpg';
        }
        
        ksort($aImages);
        
        $aPdfs = array();
        $pattern = '#-(\d+)\.jpg$#';        
        foreach ($aImages as $singleImage) {
            if (!preg_match($pattern, $singleImage, $pageMatch)) {
                continue;
            }
            $sPdf->createPdf($singleImage);
            $aPdfs[$pageMatch[1]] = preg_replace('#\.jpg#', '.pdf', $singleImage);
        }
        ksort($aPdfs);
        
        $localBrochurePath = $sPdf->merge($aPdfs, $localPath);
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        
        $eBrochure->setUrl($sHttp->generatePublicHttpUrl($localBrochurePath))
                ->setTitle('Monats Angebote')
                ->setVariety('leaflet')
                ->setBrochureNumber(substr($brochurePathMatch[1],0, 25))
                ->setTags('Sneaker, Pumps, Herren, Damen, Kinder');
        
        $cBrochures->addElement($eBrochure);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
