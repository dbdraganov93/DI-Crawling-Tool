<?php

/*
 * Brochure Crawler für DennsBiomarkt (ID: 29068)
 */

class Crawler_Company_DennsBiomarkt_Brochure extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId): Crawler_Generic_Response
    {
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPage = new Marktjagd_Service_Input_Page();
        $baseUrl = 'https://www.denns-biomarkt.de/angebote/';

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $validityPattern = '#Gültig\s*bis\s*(?<validity>\d{2}.\d{2}.\d{4})[^?]*#';
        if(!preg_match($validityPattern, $page, $validityMatch)) {
            throw new Exception('No valid date found for PDF on: ' . $baseUrl);
        }

        $pdfPattern = '#(?=href="(?<url>[^"]*))#';
        if(!preg_match($pdfPattern, $validityMatch[0], $pdfMatch)) {
            throw new Exception('No valid PDF file found on: ' . $baseUrl);
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle("Denn's Handzettel")
            ->setEnd($validityMatch['validity'])
            ->setVariety('leaflet')
            ->setUrl($pdfMatch['url']);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }
}
