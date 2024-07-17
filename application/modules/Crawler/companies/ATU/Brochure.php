<?php

/*
 * Brochure Crawler für A.T.U (ID: 83)
 */

class Crawler_Company_ATU_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.atu.de/';
        $searchUrl = $baseUrl . 'home';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?page2flip[^"]+?)"#';
        if (!preg_match($pattern, $page, $brochureInfoMatch)) {
            throw new Exception($companyId . ': unable to get brochure info url.');
        }

        $brochureBaseUrl = preg_replace('#\/([^\.\/]+?\.html\#?\/?\d?)$#', '/', $brochureInfoMatch[1]);

        $sPage->open($brochureBaseUrl . 'mobile.xml');
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<pdf>([^<]+?)</pdf>#';
        if (!preg_match($pattern, $page, $pdfPathMatch)) {
            throw new Exception($companyId . ': unable to get brochure pdf url.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($brochureBaseUrl . $pdfPathMatch[1])
                ->setTitle('Monatsangebote')
                ->setEnd(date('d.m.Y', strtotime('last friday of this month')))
                ->setVariety('leaflet')
                ->setBrochureNumber(date('m_Y'));

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
