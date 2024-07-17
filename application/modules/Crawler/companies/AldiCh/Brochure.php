<?php

/* 
 * Prospekt Crawler für Aldi CH (ID: 72133)
 */

class Crawler_Company_AldiCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.aldi-suisse.ch/';
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTranslation = new Marktjagd_Service_Text_Translation();

        $aLanguages = array(
            'de' => array('title' => 'Wochenangebote'),
            'fr' => array('title' => 'Offres hebdomadaires'),
            'it' => array('title' => 'Offerte settimanali')
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguages as $singleLanguage => $singleInfo) {
            $searchUrl = $baseUrl . $singleLanguage . '/aktionen/aldi-woche-broschueren/';
            $strStoreNumbers = '';
            $count = 0;
            foreach ($sApi->findStoresByCompany($companyId)->getElements() as $eStore) {
                if (preg_match('#^' . $singleLanguage . '$#', $sTranslation->findLanguageCodeForZipcode($eStore->getZipcode()))) {
                    if (strlen($strStoreNumbers)) {
                        $strStoreNumbers .= ',';
                    }
                    $strStoreNumbers .= $eStore->getStoreNumber();
                    $count++;
                }
            }
            $this->_logger->info($companyId . ': amount store for language ' . $singleLanguage . ': ' . $count);

            foreach ($this->getDomElementByClassFromUrl($searchUrl, "csc-textpic-imagewrap") as $brochure) {
                foreach ($brochure->getElementsByTagName('a') as $link) {
                    $pdfLink = $link->getAttribute('href');
                    if (!preg_match('#_(KW(\d{1,2}).*?(\d{2,4})[^\/]+)#', $pdfLink, $match)) {
                        $this->_logger->err("link can not created: $pdfLink");
                        continue;
                    }

                    $eBrochure = new Marktjagd_Entity_Api_Brochure();
                    $eBrochure->setUrl($pdfLink . "Flipbook_$match[1].pdf")
                        ->setBrochureNumber($match[1])
                        ->setTitle($singleInfo['title'])
                        ->setStart(date('d.m.Y', strtotime($match[3] . "W$match[2] +3 day")))
                        ->setEnd(date('d.m.Y', strtotime($match[3] . "W$match[2] +1 week +2 day")))
                        ->setVariety('leaflet')
                        ->setStoreNumber($strStoreNumbers)
                        ->setLanguageCode($singleLanguage);

                    $cBrochures->addElement($eBrochure);
                }
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    /**
     * @param string $url
     * @param string $classname
     * @param string $element
     * @return DOMNodeList
     * @throws Exception
     */
    private function getDomElementByClassFromUrl($url, $classname, $element = 'div')
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($url);
        $page = $sPage->getPage()->getResponseBody();

        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $page);

        $finder = new DomXPath($doc);
        return $finder->query("//$element" . "[contains(@class, '$classname')]");
    }
}