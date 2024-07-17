<?php
/**
 * Brochure crawler for Butler's (ID: 67795)
 */

class Crawler_Company_Butlers_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.butlers.com/';
        $catalogUrl = $baseUrl . 'pages/katalog';
        $sPage = new Marktjagd_Service_Input_Page();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#\.pdf$#', $singleRemoteFile)) {
                $localBrochure = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $jInfos = $sPdf->getAnnotationInfos($localBrochure);

        $sPage->open($catalogUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#<script[^>]*data-publication="https:\/\/view\.publitas\.com\/butlers-de\/([^"]+?)"#', $page, $brochureMatch)) {
            throw new Exception($companyId . ': unable to get brochure name.');
        }
        $brochureName = $brochureMatch[1];

        for ($i = 1; $i <= 44; $i++) {
            $clickoutUrl = 'https://view.publitas.com/butlers-de/' . $brochureName . '/page/' . $i . '/hotspots_data.json';
            $sPage->open($clickoutUrl);
            $jClickouts = $sPage->getPage()->getResponseAsJson();

            $pageHeight = $jInfos[$i - 1]->height;
            $pageWidth = $jInfos[$i - 1]->width;

            foreach ($jClickouts->hotspots as $singleClickout) {
                if (!preg_match('#product#', $singleClickout->type)) {
                    continue;
                }

                $aCoordsToLink[] = [
                    'page' => $i - 1,
                    'height' => $pageHeight,
                    'width' => $pageWidth,
                    'startX' => $singleClickout->position->left * $pageWidth,
                    'endX' => ($singleClickout->position->left + $singleClickout->position->width) * $pageWidth,
                    'startY' => $pageHeight - $singleClickout->position->top * $pageHeight,
                    'endY' => $pageHeight - ($singleClickout->position->top + $singleClickout->position->height) * $pageHeight,
                    'link' => $singleClickout->products[0]->webshopUrl
                ];
            }
        }

        $coordFileName = $localPath . 'coordinates_' . $companyId . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($aCoordsToLink));
        fclose($fh);

        $linkedBrochure = $sPdf->setAnnotations($localBrochure, $coordFileName);
        Zend_Debug::dump($linkedBrochure);
        die;
    }
}