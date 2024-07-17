<?php

/*
 * Prospekt Crawler für Gravis (ID: 29034)
 */

class Crawler_Company_Gravis_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.gravis.de/';
        $searchUrl = $baseUrl . 'Aktuelles/Monats-Empfehlungen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]data-configid="([^"]+?)"[^>]*class="issuuembed[^"]*"#';
        if (!preg_match($pattern, $page, $configMatch)) {
            throw new Exception($companyId . ': unable to get brochure config id.');
        }

        $configUrl = 'https://e.issuu.com/embed/' . $configMatch[1] . '.json';

        $sPage->open($configUrl);
        $jInfo = $sPage->getPage()->getResponseAsJson();

        $brochureUrl = 'https://api.issuu.com/query?action=issuu.document.download_external&documentId=' . $jInfo->id . '&format=json';

        $sPage->open($brochureUrl);
        $jBrochurePath = $sPage->getPage()->getResponseAsJson();

        if (!preg_match('#ok#', $jBrochurePath->rsp->stat)) {
            throw new Exception($companyId . ': unable to get brochure path.');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $jBrochurePath->rsp->_content->redirect->url);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);

        curl_close($ch);

        $localFolderName = APPLICATION_PATH . '/../public/files/http/' . $companyId . '/' . date('Y-m-d-H-i-s') . '/';

        if (!is_dir($localFolderName)) {
            mkdir($localFolderName, 0775, true);
        }

        $fh = fopen($localFolderName . 'brochure.pdf', 'w+');
        fwrite($fh, $result);
        fclose($fh);

        $sFtp->connect($companyId);

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Umfrage#', $singleFile)) {
                $localSurveyPath = $sFtp->downloadFtpToDir($singleFile, $localFolderName);
                break;
            }
        }

        $localFilePath = $sPdf->insert($localFolderName . 'brochure.pdf', $localSurveyPath, 2);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Monatsangebote')
            ->setUrl(preg_replace('#.+(\/files.+)#', 'https://di-gui.marktjagd.de$1', $localFilePath))
            ->setEnd(date('d.m.Y', strtotime('last day of this month')))
            ->setTags('Smartphone, Handy, Tablet, E-Book, PC, Laptop, Zubehör')
            ->setVariety('leaflet')
            ->setBrochureNumber(date('m') . '_' . $sTimes->getWeeksYear());

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
