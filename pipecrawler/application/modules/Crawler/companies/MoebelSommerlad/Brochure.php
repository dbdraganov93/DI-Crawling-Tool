<?php
/**
 * Prospekt Crawler für Möbelstadt Sommerlad (ID: 28635)
 */

class Crawler_Company_MoebelSommerlad_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.sommerlad.de/';
        $searchUrl = $baseUrl . 'angebote/prospekte';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#data-controllerUrl="\/([^"]+?)"[^>]*data-availableDevices="3,4"#';
        if (!preg_match($pattern, $page, $brochureControllerUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure controller url.');
        }

        $sPage->open($baseUrl . $brochureControllerUrlMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/files\/prospekte\/([^"]+?(MH[^"]+?))\/index[^"]+?"[^>]*class="prospect--link"#';
        if (!preg_match($pattern, $page, $brochureNumberMatch)) {
            throw new Exception($companyId . ': unable to get brochure number.');
        }

        $brochureUrl = $baseUrl . 'files/prospekte/' . $brochureNumberMatch[1] . '/pubData/source/' . $brochureNumberMatch[2] . '.pdf';

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $localBrochurePath = $sHttp->getRemoteFile($brochureUrl, $localPath);

        $siteText = json_decode($sPdf->extractText($localBrochurePath));

        $pattern = '#gültig\s*([^–]+?)\s*–\s*([^\s]+?)\s#i';
        if (!preg_match($pattern, $siteText[0]->text, $validityMatch)) {
            throw new Exception($companyId . ': unable to get brochure validity.');
        }

        if (!preg_match('#\d{2}\.d{2}#', $validityMatch[1])) {
            $validityMatch[1] .= '.' . date('m', strtotime($validityMatch[2]));
        }

        if (!preg_match('#\d{4}$#', $validityMatch[1])) {
            $validityMatch[1] .= $sTimes->getWeeksYear();

        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setBrochureNumber($brochureNumberMatch[1])
            ->setTitle('Möbelangebote')
            ->setStart($validityMatch[1])
            ->setEnd($validityMatch[2])
            ->setUrl($sHttp->generatePublicHttpUrl($localBrochurePath))
            ->setVariety('leaflet')
            ->setStoreNumber('46fa6c1710a0d4ef2aa85a0151ddf200');

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}