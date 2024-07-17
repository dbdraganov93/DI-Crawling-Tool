<?php

/**
 * Prospekt Crawler für Müller Drogerie (ID: 102)
 */
class Crawler_Company_Mueller_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.mueller.de/';
        $searchUrl = $baseUrl . 'prospekte/';
        $sPage = new Marktjagd_Service_Input_Page();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $doc = $this->createDomDocument($page);
        $xpath = new DOMXPath($doc);

        $mainNode = $xpath->query('//div[@class="mu-multi-link-teaser__content-wrapper"]');

        foreach($mainNode as $node){
            $text = $node->textContent;

            $pattern = '#\s*von\s*(?<fromDate>\d{2}.\d{2}.)(\s*bis\s*|-\s*)(?<toDate>\d{2}.\d{2}.\d{4})#';
            if(!preg_match($pattern, $text, $validityMatch)) {
                $this->_logger->alert('No validity found for brochure: ' . $text);
                continue;
            }

            $class = '//a[@class="mu-multi-link-teaser__button mu-multi-link-teaser__button--horizontal mu-multi-link-teaser__button--size_25 | mu-button"]';
            $pdfNodes = $xpath->query($node->getNodePath() . $class);
            foreach ($pdfNodes as $pdfNode) {
                $pdfNodeText = $pdfNode->textContent;

                if(preg_match('#PDF ansehen#', $pdfNodeText, $pdfMatch)) {
                    $pdfUrl = $baseUrl . $pdfNode->getAttribute('href') . '.pdf';
                }
            }

            if(empty($pdfMatch) || empty($pdfUrl)){
                $this->_logger->alert('No PDF found for brochure: ' . $text);
                continue;
            }

            $titleClass = '//h2[@class="mu-multi-link-teaser__headline mu-multi-link-teaser__headline--horizontal"]';
            $title = $xpath->query($node->getNodePath() . $titleClass)->item(0)->textContent;
            if(empty($title)){
                $this->_logger->alert('No title found for brochure: ' . $text);
                $title = 'Wochenangebote';
            }

            $startDate = $validityMatch['fromDate'] . date("Y");

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setUrl($pdfUrl)
                ->setBrochureNumber($validityMatch['fromDate'] . ' ' . $validityMatch['toDate'])
                ->setTitle('Müller Drogerie: ' . $title)
                ->setStart($startDate)
                ->setVisibleStart($startDate)
                ->setEnd($validityMatch['toDate'])
                ->setVariety('leaflet')
            ;

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function createDomDocument(string $url): DOMDocument
    {
        // ignore warnings
        $old_libxml_error = libxml_use_internal_errors(true);
        $domDoc = new DOMDocument();
        $domDoc->loadHTML($url);
        libxml_use_internal_errors($old_libxml_error);

        return $domDoc;
    }
}
