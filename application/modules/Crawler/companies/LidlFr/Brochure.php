<?php

/**
 * Brochure Crawler für Lidl FR (ID: 72305)
 */
class Crawler_Company_LidlFr_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://media.lidl-flyer.com/';
        $searchUrl = $baseUrl . 'overview/fr-FR.json';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $jInfos = $sPage->getPage()->getResponseAsJson();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($jInfos->categories as $singleJBrochureCategorie) {
            foreach ($singleJBrochureCategorie->subcategories as $singleJBrochureSubCategorie) {
                if (!preg_match('#Catalogues\s*de\s*la\s*semaine#i', $singleJBrochureSubCategorie->name)) {
                    continue;
                }
                foreach ($singleJBrochureSubCategorie->flyers as $singleJBrochure) {
                    if (strtotime('now') > strtotime($singleJBrochure->endDate)) {
                        continue;
                    }

                    $eBrochure = new Marktjagd_Entity_Api_Brochure();

                    $eBrochure->setTitle($singleJBrochure->name)
                        ->setVisibleStart($singleJBrochure->startDate)
                        ->setStart(date('d.m.Y', strtotime($eBrochure->getVisibleStart() . '+ 7 days')))
                        ->setEnd($singleJBrochure->endDate)
                        ->setUrl($singleJBrochure->pdfUrl)
                        ->setVariety('leaflet')
                        ->setBrochureNumber(substr($singleJBrochure->id, 0, 20))
                        ->setLanguageCode('fr');

                    $cBrochures->addElement($eBrochure);
                }
            }

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
