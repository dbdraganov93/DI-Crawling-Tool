<?php

/**
 * Prospekt Crawler für Müller Drogerie CH (ID: 72249)
 */
class Crawler_Company_MuellerCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.mueller.ch/';
        $searchUrl = $baseUrl . 'prospekte/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#>\s*([^<]+?)\s*<\/h2>(.+?)pdf\s*ansehen#i';
        $pattern2 = '#(Derzeit\s*gibt\s*es\s*keine\s*Prospekte)#mis';
        if (!preg_match($pattern, $page, $brochureInfoMatch) && preg_match($pattern2, $page)) {
            $this->_response->setIsImport(FALSE);
            $this->_response->setLoggingCode(4);
            return $this->_response;
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $pattern = '#gültig[^\d]+?(\d[^-]+?)\s*-\s*(\d[^<]+?)\s*<#i';
        if (!preg_match($pattern, $brochureInfoMatch[2], $validityMatch)) {
            throw new Exception($companyId . ': unable to get validity for weekly brochure.');
        }

        $pattern = '#<a[^>]*href="([^"]+?\.pdf)[?"]#';
        if (!preg_match($pattern, $brochureInfoMatch[2], $pdfUrlMatch)) {
            throw new Exception($companyId . ': unable to get pdf url.');
        }

        if (!preg_match('#\d{4}$#', $validityMatch[1])) {
            $validityMatch[1] .= $sTimes->getWeeksYear();
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($baseUrl . $pdfUrlMatch[1])
            ->setTitle($brochureInfoMatch[1])
            ->setStart($validityMatch[1])
            ->setEnd($validityMatch[2])
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
