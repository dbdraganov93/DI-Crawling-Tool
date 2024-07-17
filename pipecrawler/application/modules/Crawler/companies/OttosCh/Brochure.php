<?php

/*
 * Prospekt Crawler für OTTO'S CH (ID: 72157)
 */

class Crawler_Company_OttosCh_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.ottos.ch/';
        $aLanguages = array(
            'de' => array('title' => 'Wochenangebote'),
            'fr' => array('title' => 'Offres hebdomadaires'),
            'it' => array('title' => 'Offerte settimanali')
        );
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sTranslation = new Marktjagd_Service_Text_Translation();

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cStores = $sApi->findStoresByCompany($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguages as $languageKey => $singleInfo) {
            $sPage->open($baseUrl . $languageKey);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<section[^>]*class="content\s*weeklypaper"[^>]*>(.+?)</section>#';
            if (!preg_match($pattern, $page, $brochureListMatch)) {
                throw new Exception($companyId . ': unable to get brochure list: ' . $languageKey);
            }
            
            $pattern = '#<a[^>]*href="(https?:\/\/issuu\.com[^"]+?)"#';            
            if (!preg_match_all($pattern, $brochureListMatch[1], $brochurePathNameMatches)) {
                throw new Exception($companyId . ': unable to get brochure path name: ' . $languageKey);
            }

            if (count($brochurePathNameMatches[1]) < 2) {
                continue;
            }
            
            $sPage->open(end($brochurePathNameMatches[1]));
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#embedConfig:\s*(.+?),\s*};#';
            if (!preg_match($pattern, $page, $configMatch)) {
                throw new Exception($companyId . ': unable to get brochure config: ' . $languageKey);
            }

            $jInfo = json_decode($configMatch[1]);

            $sPage->open('https://api.issuu.com/query?action=issuu.document.download_external&documentId=' . $jInfo->documentId . '&format=json');
            $jBrochureUrl = $sPage->getPage()->getResponseAsJson();

            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            $localBrochurePath = $sHttp->getRemoteFile($jBrochureUrl->rsp->_content->redirect->url, $localPath);

            rename($localBrochurePath, $localPath . $languageKey . '.pdf');

            $localBrochurePath = $localPath . $languageKey . '.pdf';
            
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            
            $eBrochure->setUrl($sHttp->generatePublicHttpUrl($localBrochurePath))
                    ->setTitle($singleInfo['title'])
                    ->setVariety('leaflet')
                    ->setLanguageCode($languageKey)
                    ->setStart(date('d.m.Y', strtotime('wednesday next week')))
                    ->setEnd(date('d.m.Y', strtotime($eBrochure->getStart() . '+ 6 days')));

            $aZipcode = $sTranslation->findZipcodesForLanguageCode($languageKey);

            $sStoreNumbers = '';
            /* @var $eStore Marktjagd_Entity_Api_Store */
            foreach ($cStores->getElements() as $eStore) {
                if (in_array(trim($eStore->getZipcode()), $aZipcode)) {
                    if (strlen($sStoreNumbers)) {
                        $sStoreNumbers .= ', ';
                    }

                    $sStoreNumbers .= $eStore->getStoreNumber();
                }
            }

            $eBrochure->setStoreNumber($sStoreNumbers);
            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
