<?php

/*
 * Brochure Crawler für Bingo CH (ID: 72213)
 */

class Crawler_Company_BingoCh_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $aLanguages = array(
            'de' => array('searchPattern' => 'Jetzt\s*blättern',
                'tags' => 'Damen, Herren, Pump, Kinder, Sneaker, nike, Puma',
                'title' => 'Monatsangebote'),
            'fr' => array('searchPattern' => 'feuilleter\s*maintenant',
                'tags' => 'femmes, hommes, pompes, enfants, nike, Puma',
                'title' => 'Offres de mois')
        );
        $sPage = new Marktjagd_Service_Input_Page();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        
        $cStores = $sApi->findStoresByCompany($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguages as $singleLanguage => $singleInfo) {
            $baseUrl = 'http://' . $singleLanguage . '.bingo-shoes.ch/';
            $searchUrl = $baseUrl . 'prospekt';

            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>' . $singleInfo['searchPattern'] . '#';
            if (!preg_match($pattern, $page, $brochurePathNameMatch)) {
                throw new Exception($companyId . ': unable to get brochure path name.');
            }

            $sPage->open($brochurePathNameMatch[1]);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<meta[^>]*name="description"[^>]*content="([^"]+?)"#';
            if (!preg_match($pattern, $page, $brochureInfoMatch)) {
                throw new Exception($companyId . ': unable to get brochure info.');
            }

            $pattern = '#<meta[^>]*property="og:image"[^>]*content="([^"]+?page_\d+[^"]+?)"#';
            if (!preg_match($pattern, $page, $brochureImageMatch)) {
                throw new Exception($companyId . ': unable to get brochure image url.');
            }

            $sHttp = new Marktjagd_Service_Transfer_Http();

            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            $i = 1;
            while (TRUE) {
                if (!$sPage->checkUrlReachability(preg_replace('#page_\d+#', 'page_' . $i, $brochureImageMatch[1]))) {
                    break;
                }
                $sHttp->getRemoteFile(preg_replace('#page_\d+#', 'page_' . $i++, $brochureImageMatch[1]), $localPath);
            }

            $sPdf = new Marktjagd_Service_Output_Pdf();
            foreach (scandir($localPath) as $singleFile) {
                if (preg_match('#_(\d+)\.jpg#', $singleFile, $siteMatch)) {
                    $sPdf->createPdf($localPath . $singleFile);
                }
            }

            $aPdfSites = array();
            foreach (scandir($localPath) as $singleFile) {
                if (preg_match('#_(\d+)\.pdf#', $singleFile, $siteMatch)) {
                    $aPdfSites[$siteMatch[1]] = $localPath . $singleFile;
                }
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($sHttp->generatePublicHttpUrl($sPdf->merge($aPdfSites, $localPath)))
                    ->setTitle($singleInfo['title'])
                    ->setVariety('customer_magazine')
                    ->setTags($singleInfo['tags'])
                    ->setLanguageCode($singleLanguage)
                    ->setStart(date('d.m.Y', strtotime('first day of this month')))
                    ->setEnd(date('d.m.Y', strtotime('last day of this month')))
                    ->setBrochureNumber(date('m', strtotime('this month')) . '_' . date('Y', strtotime('this month')) . '_' . $singleLanguage);
            
            $aZipcode = $sTranslation->findZipcodesForLanguageCode($singleLanguage);

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
