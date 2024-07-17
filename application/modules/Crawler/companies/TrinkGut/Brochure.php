<?php

/*
 * Prospekt Crawler für Trink Gut (ID: 22241)
 */

class Crawler_Company_TrinkGut_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.trinkgut.de/';
        $searchUrl = $baseUrl . 'blaetterkatalog';
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);

        $cStores = $sApi->findStoresByCompany($companyId);
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cStores->getElements() as $eStore) {
            $ch = curl_init($searchUrl);
            curl_setopt($ch, CURLOPT_HEADER, TRUE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_COOKIE, 'TGMarktID=208; trinkgut-market=' . $eStore->getStoreNumber() . '; __csrf_token-1=26k5ok9uZLzsJ6DxHQxZCHkzlTT3Qb;');
            $result = curl_exec($ch);

            $pattern = '#<div[^>]*class="flipbook"[^>]*>(.+?)</div>\s*</div>\s*</div>\s*</div>#s';
            if (!preg_match($pattern, $result, $pageListMatch)) {
                $this->_logger->info($companyId . ': unable to get brochure page list: ' . $eStore->getStoreNumber());
                continue;
            }

            $pattern = '#<img[^>]*src="([^"]+?\/(\d{4})-([^\/]+?)\/\d+\/(\d+)\.[^"]+?)"#';
            if (!preg_match_all($pattern, $pageListMatch[1], $pageMatches)) {
                throw new Exception($companyId . ': unable to get any brochure pages from list.');
            }
            
            $aUrls = array_unique($pageMatches[1]);
            $aWeek = array_unique($pageMatches[3]);
            $aBrochurePages = array_unique($pageMatches[4]);

            foreach ($aWeek as $singleWeek) {
                if (!file_exists($localPath . '/' . $singleWeek)) {
                    if (mkdir($localPath . '/' . $singleWeek, 0777)) {
                        $this->_logger->info($companyId . ': folder ' . $localPath . $singleWeek . ' created.');
                    }
                }
            }

            foreach ($aBrochurePages as $singleBrochurePage) {
                if (!file_exists($localPath . '/' . $singleWeek . '/' . $singleBrochurePage)) {
                    if (mkdir($localPath . '/' . $singleWeek . '/' . $singleBrochurePage, 0777)) {
                        $this->_logger->info($companyId . ': folder ' . $localPath . $singleWeek . '/' . $singleBrochurePage . ' created.');
                    }
                }
            }

            foreach ($aUrls as $singleUrl) {
                $pattern = '#(\d+\.jpeg)$#';
                if (preg_match($pattern, $singleUrl, $fileNameMatch)) {
                    if (!file_exists($localPath . $singleWeek . '/' . $singleBrochurePage . '/' . $fileNameMatch[1])) {
                        $sHttp->getRemoteFile($singleUrl, $localPath . $singleWeek . '/' . $singleBrochurePage . '/');
                    }
                }
            }

            $aPdfsToMerge = array();
            foreach (scandir($localPath . $singleWeek . '/' . $singleBrochurePage) as $singleImage) {
                if (!preg_match('#^\.#', $singleImage) && preg_match('#(\d+)\.jpeg#', $singleImage, $pageMatch)) {
                    $sPdf->createPdf($localPath . $singleWeek . '/' . $singleBrochurePage . '/' . $singleImage);
                }
            }

            foreach (scandir($localPath . $singleWeek . '/' . $singleBrochurePage) as $singleImage) {
                if (!preg_match('#^\.#', $singleImage) && preg_match('#(\d+)\.pdf#', $singleImage, $pageMatch)) {
                    $aPdfsToMerge[$pageMatch[1]] = $localPath . $singleWeek . '/' . $singleBrochurePage . '/' . $singleImage;
                }
            }

            $pdfFilePath = $localPath . $singleWeek . '/' . $singleBrochurePage . md5(implode(',', $aWeek) . implode(',', $aBrochurePages)) . '.pdf';

            $pdfFilePath = $sPdf->merge($aPdfsToMerge, $localPath . $singleWeek . '/' . $singleBrochurePage);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($sCsv->generatePublicBrochurePath($pdfFilePath))
                    ->setTitle('Wochen Angebote')
                    ->setStart($sTimes->findDateForWeekday($pageMatches[2][0], $pageMatches[3][0], 'Mo'))
                    ->setEnd($sTimes->findDateForWeekday($pageMatches[2][0], $pageMatches[3][0], 'Sa'))
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet')
                    ->setStoreNumber($eStore->getStoreNumber());
            
            $cBrochures->addElement($eBrochure);
        }

        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
