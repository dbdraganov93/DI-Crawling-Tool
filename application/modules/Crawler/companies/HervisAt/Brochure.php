<?php

/*
 * Prospekt Crawler für Hervis AT (ID: 72287)
 */

class Crawler_Company_HervisAt_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.hervis.at/';
        $searchUrl = $baseUrl . 'store/flugblatt';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?fliphtml[^"]+?)"#';
        if (!preg_match($pattern, $page, $imagePathMatch)) {
            throw new Exception($companyId . ': unable to get brochure image path.');
        }

        $pattern = '#bis\s*(\d{2}\.\d{2}\.\d{4})\.?\s*#i';
        if (!preg_match($pattern, $page, $validityEndMatch)) {
            throw new Exception($companyId . ': unable to get brochure validity end.');
        }

        $siteNo = 1;
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        while (TRUE) {
            $brochureImagePageUrl = $imagePathMatch[1] . 'files/large/' . $siteNo . '.jpg';
            if (!$sHttp->getRemoteFile($brochureImagePageUrl, $localPath)) {
                break;
            }

            $siteNo++;
        }

        $aPdfs = array();
        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#(\d+)\.jpg#', $singleFile, $siteMatch)) {
                $sPdf->createPdf($localPath . $singleFile)[0];
                foreach (scandir($localPath) as $singlePdfFile) {
                    if (preg_match('#' . $siteMatch[1] . '\.pdf#', $singlePdfFile)) {
                        $aPdfs[$siteMatch[1] - 1] = $localPath . $singlePdfFile;
                    }
                }
            }
        }
        ksort($aPdfs);

        $localPdfPath = $sPdf->merge($aPdfs, $localPath);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Flugblatt')
                ->setUrl($sHttp->generatePublicHttpUrl($localPdfPath))
                ->setEnd($validityEndMatch[1])
                ->setVariety('leaflet')
                ->setTags('Outdoor, Ausrüstung, Bekleidung, Camping, Fitness, Damen, Herren, Kinder');

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
