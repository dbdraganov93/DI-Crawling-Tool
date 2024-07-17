<?php
/**
 * Brochure Crawler für Go Sport FR (ID: 72385)
 */

class Crawler_Company_GoSportFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.go-sport.com/';
        $searchUrl = $baseUrl . 'guides';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<strong[^>]*>\s*([^<]+?)\s*<\/strong>\s*<br[^>]*>\s*<iframe[^>]*src="[^"]*bkcode=([^\&]+?)\&#';
        if (!preg_match_all($pattern, $page, $brochureNumberMatches)) {
            throw new Exception($companyId . ': unable to get any brochure numbers.');
        }

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        for ($i = 0; $i < count($brochureNumberMatches[0]); $i++) {
            $brochureDownloadUrl = 'https://d.calameo.com/pinwheel/download/get?output=redirect&code=' . $brochureNumberMatches[2][$i] . '&bkcode=' . $brochureNumberMatches[2][$i];

            $ch = curl_init($brochureDownloadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
            $result = curl_exec($ch);
            curl_close($ch);

            $filePath = $localPath . $brochureNumberMatches[2][$i] . '.pdf';

            $fh = fopen($filePath, 'w+');
            fwrite($fh, $result);
            fclose($fh);

            $brochurePath = $sHttp->generatePublicHttpUrl($filePath);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($brochureNumberMatches[1][$i])
                ->setUrl($brochurePath)
                ->setVariety('leaflet')
                ->setBrochureNumber($brochureNumberMatches[2][$i])
                ->setTags('natation, chaussettes, vélo, chaise de camping, dos, balance');

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);

    }
}