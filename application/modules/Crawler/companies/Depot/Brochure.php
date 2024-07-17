<?php
/**
 * Brochure Crawler für Depot (ID: 22304)
 */

class Crawler_Company_Depot_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.depot-online.com/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $sPage->open($baseUrl);
        $basePage = $sPage->getPage()->getResponseBody();

        $pattern='#<a [^>]*(innertext=\"Kataloge.+?Flyer\").+?href=\"([^\"]+?)\">#';
        if (!preg_match_all($pattern, $basePage, $brochureUrl)) {
            throw new Exception($companyId . ': unable to get brochure-path.');
        }
        $searchUrl = $brochureUrl[2][0];

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a [^>]*href="(https://bit.ly/[A-Za-z1-9]{7})#';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $downloadedPdfs = [];
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        for ($i = 0; $i < count($brochureMatches[1]); $i++ ) {
            if(!array_key_exists($brochureMatches[1][$i], $downloadedPdfs)){
                $downloadedPdfs[$brochureMatches[1][$i]] = $brochureMatches[1][$i];
            } else {
                continue;
            }

            $localBrochurePath = $sHttp->getRemoteFile($this->getFullBrochureUrl($brochureMatches[1][$i]), $localPath);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($localBrochurePath)
                ->setTitle(basename($localBrochurePath, '.pdf'))
                ->setVariety('leaflet')
                ->setStart("01.01." . date('Y'))
                ->setEnd("31.12." . date('Y'));

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);

        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        return $this->_response->generateResponseByFileName($fileName);
    }

    private function getFullBrochureUrl(string $singleBrochure): string
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $singleBrochure);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $result = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        return $info['url'];
    }
}
