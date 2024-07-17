<?php

/* 
 * Prospekt Crawler für Budni (ID: 28980)
 */

class Crawler_Company_Budni_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.budni.de/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sWidget = new Marktjagd_Service_Input_WidgetImport();

        $pattern = '#ltig\s*vom\s*(.*?)\s*bis\s*(.*?)<#i';
        $offerUrls = [
            'angebote/hamburg', //it looks like the stores at berlin has no special brochures
        ];

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($offerUrls as $offerUrl) {
            $sPage->open($baseUrl . $offerUrl);
            $page = $sPage->getPage()->getResponseBody();
            if (!preg_match($pattern, $page, $validityMatch)) {
                $this->_logger->err("$companyId : unable to get validity brochure ($baseUrl$offerUrl)");
                continue;
            }
            $startDate = explode(' ', $validityMatch[1]);
            $endDate = explode(' ', $validityMatch[2]);
            $endDate[1] = $sTimes->findNumberForMonth($endDate[1]) . '.';
            $startDate[1] = isset($startDate[1]) ? $sTimes->findNumberForMonth($startDate[1]) . '.' : $endDate[1];
            $startDate[2] = isset($startDate[2]) ? $sTimes->findNumberForMonth($startDate[2]) . '.' : $endDate[2];

            $strStoreNumbers = '';
            foreach ($cStores as $eStore) {
                if (strlen($strStoreNumbers)) {
                    $strStoreNumbers .= ',';
                }
                $strStoreNumbers .= $eStore->getStoreNumber();
            }

            foreach ($sWidget->getBrochureFromIssuu($baseUrl . $offerUrl) as $brochureId => $filePath) {
                $eBrochure = new Marktjagd_Entity_Api_Brochure();
                $eBrochure->setUrl($filePath)
                    ->setTitle('Aktuelle Angebote bei BUDNI')
                    ->setStart(implode($startDate))
                    ->setEnd(implode($endDate))
                    ->setVariety('leaflet')
                    ->setBrochureNumber($brochureId)
                    ->setStoreNumber($strStoreNumbers);
                $cBrochures->addElement($eBrochure);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        return $this->_response->generateResponseByFileName($fileName);
    }
}