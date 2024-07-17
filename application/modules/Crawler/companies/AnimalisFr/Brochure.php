<?php
/**
 * Brochure Crawler für Animalis FR (ID: 72346)
 */

class Crawler_Company_AnimalisFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.animalis.com/';
        $searchUrl = $baseUrl . 'nos-magazines.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="magazines-main"[^>]*>\s*<ul[^>]*>(.+?)<\/ul#';
        if (!preg_match($pattern, $page, $brochureListMatch)) {
            throw new Exception($companyId . ': unable to get brochure list.');
        }

        $pattern = '#<a[^>]*>\s*<img[^>]*title="Catalogue\s*(\d+)#';
        if (!preg_match_all($pattern, $brochureListMatch[1], $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures from list.');
        }

        $mostActualBrochure = $brochureMatches[0][key($brochureMatches[1])];

        $pattern = '#<a[^>]*href="([^"]+?)"#';
        if (!preg_match($pattern, $mostActualBrochure, $urlMatch)) {
            throw new Exception($companyId . ': unable to get brochure url: ' . $mostActualBrochure);
        }

        $sPage->open($urlMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<li[^>]*>\s*<strong[^>]*>\s*([^<]+?)\s*<#';
        if (!preg_match($pattern, $page, $brochureTitleMatch)) {
            throw new Exception($companyId . ': unable to get brochure title: ' . $urlMatch[1]);
        }

        $pattern = '#<iframe[^>]*src="([^"]*flipbook[^>]*magazine[^"]*)"#';
        if (!preg_match($pattern, $page, $brochureInfoUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure info url: ' . $urlMatch[1]);
        }

        $sPage->open($brochureInfoUrlMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#module\s*:\s*\"flipbook"\s*,\s*config\s*:\s*(.+?\}\}\}),#';
        if (!preg_match($pattern, $page, $brochureInfoListMatch)) {
            throw new Exception($companyId . ': unable to get brochure info list: ' . $brochureInfoUrlMatch[1]);
        }

        $jBrochureInfo = json_decode($brochureInfoListMatch[1]);

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $aFiles = array();

        foreach ($jBrochureInfo->pages as $singlePage) {
            $downloadUrl = 'https://app-flipbook.maps-system.com/media/get_file.php?path=/home/maps/app-flipbook.maps-system.com/data/'
                . $jBrochureInfo->images . '/pages/large/'
                . str_pad($singlePage->numPage - 1, 4, '0', STR_PAD_LEFT) . '.jpg';

            $aFiles[$singlePage->numPage - 1] = $sHttp->getRemoteFile($downloadUrl, $localPath . 'site_' . ($singlePage->numPage - 1));
        }
        foreach ($aFiles as $site => $singleFile) {
            rename($singleFile, $localPath . $site . '.jpg');
        }
        foreach (scandir($localPath) as $singleFile) {
            if (preg_match('#(\d+)\.jpg$#', $singleFile, $siteNoMatch)) {
                $sPdf->createPdf($localPath . $singleFile);
                $aFiles[$siteNoMatch[1]] = $localPath . preg_replace('#\.jpg#', '.pdf', $singleFile);
            }
        }
        mkdir($localPath . 'merged/');
        $mergedPdf = $sPdf->merge($aFiles, $localPath . 'merged/');

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($sHttp->generatePublicHttpUrl($mergedPdf))
            ->setTitle($brochureTitleMatch[1])
            ->setVariety('leaflet')
            ->setTags('chiens, chats, aquariophilie, oiseaux, rongeurs, reptiles, animaux de la ferme, animaux de la nature');

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}