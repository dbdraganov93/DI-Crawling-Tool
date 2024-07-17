<?php
/**
 * Brochure Crawler für Tegut (ID: 349)
 */

class Crawler_Company_Tegut_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.tegut.com/';
        $searchUrl = $baseUrl . 'angebote.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sDbGeo = new Marktjagd_Database_Service_GeoRegion();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $aCountyStores = array();
        foreach ($cStores as $eStore) {
            $strCounty = $sDbGeo->findRegionByZipCode($eStore->getZipcode());
            if (!array_key_exists(preg_replace('#\s*-\s*#', ' ', $strCounty), $aCountyStores)) {
                $aCountyStores[preg_replace('#\s*-\s*#', ' ', $strCounty)] = array();
            }
            $aCountyStores[preg_replace('#\s*-\s*#', ' ', $strCounty)][] = $eStore->getStoreNumber();
        }

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="\/?(e-book[^"]+?)".+?<figcaption[^>]*>\s*([^<]+?)\s*<#';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get brochure infos.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        for ($i = 0; $i < count($brochureMatches[0]); $i++) {
            $brochureUrl = $baseUrl . $brochureMatches[1][$i];

            $sPage->open($brochureUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#downloadLink\s*:\s*\'\/([^\']+?)\'#';
            if (!preg_match($pattern, $page, $pdfUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get pdf url: ' . $brochureUrl);
                continue;
            }

            $strStoreNumbers = '';
            foreach (preg_split('#\s*(,|\&)\s*#', $brochureMatches[2][$i]) as $singleCounty) {
                if (strlen($strStoreNumbers)) {
                    $strStoreNumbers .= ',';
                }

                $strStoreNumbers .= implode(',', $aCountyStores[$singleCounty]);
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Wochenangebote')
                ->setUrl($baseUrl . $pdfUrlMatch[1])
                ->setStart(date('d.m.Y', strtotime('monday this week')))
                ->setEnd(date('d.m.Y', strtotime('saturday this week')))
                ->setStoreNumber($strStoreNumbers)
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}