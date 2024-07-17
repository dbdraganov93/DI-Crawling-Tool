<?php
/**
 * Brochure Crawler für Cora FR (ID: 72323)
 */

class Crawler_Company_CoraFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.cora.fr';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<a[^>]*href="([^"]+?)"[^>]*data-idmag="(\d+)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $aDownloadedBrochures = array();
        for ($i = 0; $i < count($storeUrlMatches[0]); $i++) {
            $storeUrl = $storeUrlMatches[1][$i];
            $storeId = $storeUrlMatches[2][$i];

            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="([^"]+?prospectus[^"]+?\/([^\/]+?)\/)\?store=[^"]+?"[^>]*>\s*<img[^>]*>\s*<\/a>\s*<div[^>]*>\s*<div[^>]*class="prospectus-periode"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $brochureMatches)) {
                $this->_logger->err($companyId . ': unable to get any brochures: ' . $storeUrl);
                continue;
            }

            for ($j = 0; $j < count($brochureMatches[0]); $j++) {
                if (!array_key_exists($brochureMatches[2][$j], $aDownloadedBrochures)) {
                    $aSites = array();
                    $localPath = $sHttp->generateLocalDownloadFolder($companyId);
                    mkdir($localPath . $brochureMatches[2][$j] . '/');
                    $localPath .= $brochureMatches[2][$j] . '/';

                    $brochureInfoUrl = $brochureMatches[1][$j] . 'params.xml';

                    $sPage->open($brochureInfoUrl);
                    $page = $sPage->getPage()->getResponseBody();

                    $xBrochureInfos = simplexml_load_string($page);

                    foreach ($xBrochureInfos->item as $singleInfo) {
                        if (preg_match('#maxpages#', (string)$singleInfo->attributes()['key'][0])) {
                            $amountSites = (string)$singleInfo->attributes()['value'][0];
                        }
                        if (preg_match('#title#', (string)$singleInfo->attributes()['key'][0])) {
                            $strTitle = (string)$singleInfo->attributes()['value'][0];
                        }
                    }

                    $aValidity = preg_split('#\s*au\s*#', preg_replace(array('#\s+janvier#', '#\s+février#', '#du\s+#'), array('.01.', '.02.', ''), $brochureMatches[3][$j]));

                    for ($site = 1; $site <= $amountSites; $site++) {
                        $sHttp->getRemoteFile($brochureMatches[1][$j] . 'page-' . $site . '.jpg', $localPath);
                        $sPdf->createPdf($localPath . 'page-' . $site . '.jpg');
                    }

                    foreach (scandir($localPath) as $singleFile) {
                        if (preg_match('#(\d+)\.pdf$#', $singleFile, $pageNoMatch)) {
                            $aSites[$pageNoMatch[1]] = $localPath . $singleFile;
                        }
                    }
                    ksort($aSites);
                    $localBrochurePath = $sPdf->merge($aSites, $localPath);

                    $aDownloadedBrochures[$brochureMatches[2][$j]] = array(
                        'path' => $localBrochurePath,
                        'title' => $strTitle,
                        'validStart' => $aValidity[0] . $sTimes->getWeeksYear(),
                        'validEnd' => $aValidity[1] . $sTimes->getWeeksYear()
                    );

                    if (strlen($aValidity[0]) <= 2 && preg_match('#(\.\d+\.)$#', $aValidity[1], $monthMatch)) {
                        $aValidity[0] .= $monthMatch[1];
                    }

                    $brochurePath = $localBrochurePath;
                    $brochureTitle = $strTitle;
                    $brochureValidStart = $aValidity[0] . $sTimes->getWeeksYear();
                    $brochureValidEnd = $aValidity[1] . $sTimes->getWeeksYear();
                } else {
                    $brochurePath = $aDownloadedBrochures[$brochureMatches[2][$j]]['path'];
                    $brochureTitle = $aDownloadedBrochures[$brochureMatches[2][$j]]['title'];
                    $brochureValidStart = $aDownloadedBrochures[$brochureMatches[2][$j]]['validStart'];
                    $brochureValidEnd = $aDownloadedBrochures[$brochureMatches[2][$j]]['validEnd'];
                }

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle($brochureTitle)
                    ->setBrochureNumber($brochureMatches[2][$j])
                    ->setUrl($sHttp->generatePublicHttpUrl($brochurePath))
                    ->setStoreNumber($storeId)
                    ->setVariety('leaflet')
                    ->setTags('détergent, fromage, nettoyeur, les vêtements féminins, savon, nourriture pour chats, chaussettes, vin, saumon, pommes');

                $cBrochures->addElement($eBrochure);

            }

        }

        return $this->getResponse($cBrochures, $companyId);

    }
}
