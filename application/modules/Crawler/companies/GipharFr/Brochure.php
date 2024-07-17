<?php
/**
 * Brochure Crawler für Giphar FR (ID: 72371)
 */

class Crawler_Company_GipharFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.pharmaciengiphar.com/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="[^"]+?\/read\/([^\/]+?)\/[^>]*>\s*<img[^>]*Giphar\s*Magazine#';
        if (!preg_match($pattern, $page, $brochureIdMatch)) {
            throw new Exception($companyId . ': unable to get brochure id for download.');
        }

        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadUrl = 'https://d.calameo.com/pinwheel/download/get?output=redirect&code=' . $brochureIdMatch[1] . '&bkcode=' . $brochureIdMatch[1];

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localBrochurePath = $sHttp->getRemoteFile($downloadUrl, $localPath);

        rename($localBrochurePath, $localPath . 'brochure.pdf');

        $localBrochurePath = $localPath . 'brochure.pdf';

        $jPdfInfos = $sPdf->extractText($localBrochurePath);

        $pattern = '#([A-Z]+?)\s*-\s*([A-ZÛ]+?)\s*(\d{4})\s*I#';
        if (!preg_match($pattern, $jPdfInfos, $validityMatch)) {
            throw new Exception($companyId . ': unable to get validity from brochure.');
        }

        $firstDay = '01.' . $sTimes->localizeDate($validityMatch[1] . ' ' . $validityMatch[3], 'fr');
        $lastDay = date('t.m.Y', strtotime('01.' . $sTimes->localizeDate($validityMatch[2] . ' ' . $validityMatch[3], 'fr')));

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('le magazine de mon pharmacien')
            ->setStart($firstDay)
            ->setEnd($lastDay)
            ->setUrl($sHttp->generatePublicHttpUrl($localBrochurePath))
            ->setBrochureNumber($validityMatch[1] . '_' . $sTimes->getWeeksYear())
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}