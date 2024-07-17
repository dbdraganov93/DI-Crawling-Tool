<?php
/**
 * Brochure Crawler für Penny AT (ID: 72742)
 */

class Crawler_Company_PennyAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.penny.at/';
        $searchUrl = $baseUrl . 'offers/browse-leaflets';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*openLeaflet[^>]*>(.+?)<\/a>#s';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($brochureMatches[1] as $singleBrochure) {
            $pattern = '#<span[^>]*>[A-Z][a-z]\s*([^<]+?)\s*<\/span>\s*-\s*<span[^>]*>[A-Z][a-z]\s*([^<]+?)\s*<\/span>#';
            if (!preg_match($pattern, $singleBrochure, $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity: ' . $singleBrochure);
                continue;
            }

            $pattern = '#<a[^>]*href="\/offers\/leaflet\/([^"]+?)"[^>]*>\s*Details#';
            if (!preg_match($pattern, $singleBrochure, $brochureUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure url: ' . $singleBrochure);
                continue;
            }

            $brochureUrl = $baseUrl . 'offers/leaflet/' . rawurlencode($brochureUrlMatch[1]) . '/';

            $sPage->open($brochureUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<iframe[^>]*d=([^\&]+)\&[^>]*u=([^"]+)"#';
            if (!preg_match($pattern, $page, $detailMatch)) {
                throw new Exception($companyId . ': unable to get brochure details');
            }
            $detailUrl = 'https://reader3.isu.pub/' . $detailMatch[2] . '/' . $detailMatch[1] . '/reader3_4.json';
            $sPage->open($detailUrl);
            $jInfos = $sPage->getPage()->getResponseAsJson();

            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            foreach ($jInfos->document->pages as $pageInfos) {
                $sHttp->getRemoteFile('http://' . $pageInfos->imageUri, $localPath);
            }

            foreach (scandir($localPath) as $pagePath) {
                if (preg_match('#\.jpg$#', $pagePath)) {
                    $sPdf->createPdf($localPath . $pagePath);
                }
            }

            $aPages = array();
            foreach (scandir($localPath) as $pagePath) {
                if (preg_match('#page_(\d+)\.pdf$#', $pagePath, $pageNoMatch)) {
                    $aPages[$pageNoMatch[1]] = $localPath . $pagePath;
                }
            }
            ksort($aPages);

            $strCompleteBrochurePath = $sPdf->merge($aPages, $localPath);
            $localFilePath = $sHttp->generatePublicHttpUrl($strCompleteBrochurePath);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($localFilePath)
                ->setTitle($brochureUrlMatch[1])
                ->setStart(preg_replace('#\.(\d{2})$#', '.20$1', $validityMatch[1]))
                ->setEnd(preg_replace('#\.(\d{2})$#', '.20$1', $validityMatch[2]))
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);

        }

        return $this->getResponse($cBrochures, $companyId);
    }
}