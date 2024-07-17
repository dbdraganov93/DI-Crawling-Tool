<?php
/**
 * Brochure Crawler für Expert AT (ID: 72783)
 */

class Crawler_Company_ExpertAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $newGenTest = TRUE;
        $baseUrl = 'https://www.expert.at/';
        $searchUrl = $baseUrl . 'aktuell';
        $brochureDetailUrl = 'https://www.yumpu.com/de/document/json/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sGoogleSpreadsheet = new Marktjagd_Service_Output_GoogleSpreadsheetWrite();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<iframe[^>]*class="flyer-iframe"[^>]*src="([^"]+?)"#';
        if (!preg_match($pattern, $page, $viewerMatch)) {
            throw new Exception($companyId . ': unable to get brochure frame.');
        }

        $sPage->open($viewerMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#var\s*magazineID\s*=\s*(\d+)\s*;#';
        if (!preg_match($pattern, $page, $brochureIdMatch)) {
            throw new Exception($companyId . ': unable to get brochure id.');
        }

        $sPage->open($brochureDetailUrl . $brochureIdMatch[1]);
        $jBrochureDetailInfo = $sPage->getPage()->getResponseAsJson();

        Zend_Debug::dump($jBrochureDetailInfo);die;

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($localBrochurePath)
            ->setTitle('Wochenangebote')
            ->setBrochureNumber($brochureIdMatch[1]);

        $cBrochures->addElement($eBrochure);

        if ($newGenTest) {
            $sGoogleSpreadsheet->addNewGen($eBrochure);
        }


        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);

    }
}