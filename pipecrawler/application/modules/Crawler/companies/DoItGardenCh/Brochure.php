<?php
/**
 * Brochure Crawler für doit+garden CH (ID: 72165)
 */

class Crawler_Company_DoItGardenCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.doitgarden.ch/';
        $searchUrl = $baseUrl . '[[LANGUAGE]]/cp/prospekte';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $sTimes = new Marktjagd_Service_Text_Times();

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $aLanguage = array(
            'de' => array('title' => 'Alle Aktionen: Aktionsprospekt'),
            'fr' => array('title' => 'Toutes les actions: Prospectus des actions'),
            'it' => array('title' => 'Tutte le azioni: Prospetto delle azioni')
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguage as $languageCode => $aLanguageInfos) {
            $singleUrl = preg_replace('#\[\[LANGUAGE\]\]#', $languageCode, $searchUrl);

            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#https?:\/\/[^"]+?\/([^\/]*flyer_kw(\d+)[^\?"\/]+?)(\/|\?|")#';
            if (!preg_match($pattern, $page, $brochureInfoUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure info url.');
                continue;
            }

            $brochureUrl = 'https://viewer.ipaper.io/doitgarden/' . $brochureInfoUrlMatch[1] . '/GetPDF.ashx';
            $localPath = $sHttp->generateLocalDownloadFolder($companyId);

            $brochurePath = $localPath . $brochureInfoUrlMatch[1] . '.pdf';

            $ch = curl_init($brochureUrl);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            $result = curl_exec($ch);
            curl_close($ch);

            $fh = fopen($brochurePath, 'w+');
            fwrite($fh, $result);
            fclose($fh);

            $strStoreNumbers = '';
            foreach ($cStores as $eStore) {
                if (preg_match('#^' . $languageCode . '$#i', $sTranslation->findLanguageCodeForZipcode($eStore->getZipcode()))) {
                    if (strlen($strStoreNumbers)) {
                        $strStoreNumbers .= ',';
                    }
                    $strStoreNumbers .= $eStore->getStoreNumber();
                }
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($sHttp->generatePublicHttpUrl($brochurePath))
                ->setTitle($aLanguageInfos['title'])
                ->setStart($sTimes->findDateForWeekday($sTimes->getWeeksYear(), $brochureInfoUrlMatch[2], 'Di'))
                ->setEnd(date('d.m.Y', strtotime($eBrochure->getStart() . '+ 13 days')))
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet')
                ->setStoreNumber($strStoreNumbers)
                ->setLanguageCode($languageCode)
                ->setBrochureNumber(preg_replace('#_#', '', $brochureInfoUrlMatch[1]));

            $cBrochures->addElement($eBrochure);

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}