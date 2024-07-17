<?php

/*
 * Brochure Crawler für Denner (CH) (ID: 72116)
 */

class Crawler_Company_DennerCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.denner.ch/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();

        $cStores = $sApi->findStoresByCompany($companyId);

        $aLanguages = array(
            'de' => array(
                'title' => 'Denner Woche',
                'tags' => 'Wurst, Fleisch, Fisch, Obst, Gemüse, Hygiene, Haustier, Haushalt'),
            'fr' => array(
                'title' => 'Hebdo Denner',
                'tags' => 'saucisse, viande, poisson, fruits, légumes, hygiène, animal de compagnie, ménage'),
            'it' => array(
                'title' => 'Settimanale Denner',
                'tags' => 'salsiccia, carne, pesce, frutta, verdura, igiene, animali domestici, famiglia')
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguages as $singleLanguage => $aBrochureInfos) {
            $sPage->open($baseUrl . $singleLanguage);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*<div[^>]*>\s*<img[^>]*title="' . $aBrochureInfos['title'] . '"#';
            if (!preg_match($pattern, $page, $brochureUrlMatch)) {
                throw new Exception($companyId . ': unable to get brochure url.');
            }

            $sPage->open($brochureUrlMatch[1]);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#window\.issuu_reader_arguments\s*=\s*\{\s*username:\s*"([^"]+?)",\s*documentName:\s*"([^"]+?)"#i';
            if (!preg_match($pattern, $page, $brochureInfoMatch)) {
                throw new Exception($companyId . ': unable to get brochure info.');
            }

            $sPage->open('https://reader3.isu.pub/' . $brochureInfoMatch[1] . '/' . $brochureInfoMatch[2] . '/reader3_4.json');
            $jInfos = $sPage->getPage()->getResponseAsJson();

            $localPath = $sHttp->generateLocalDownloadFolder($companyId);
            foreach ($jInfos->document->pages as $pageInfos) {
                $sHttp->getRemoteFile('http://' . $pageInfos->imageUri, $localPath);
            }

            foreach (scandir($localPath) as $pagePath) {
                if (preg_match('#\.jpg$#', $pagePath)) {
                    $sPdf->createPdf($localPath . $pagePath);
                }
            }

            $aPages = array();
            foreach (scandir($localPath) as $pagePath) {
                if (preg_match('#page_(\d+)\.pdf$#', $pagePath, $pageNoMatch)) {
                    $aPages[$pageNoMatch[1]] = $localPath . $pagePath;
                }
            }
            ksort($aPages);

            $strCompleteBrochurePath = $sPdf->merge($aPages, $localPath);
            $localFilePath = $sHttp->generatePublicHttpUrl($strCompleteBrochurePath);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($localFilePath)
                ->setTitle($aBrochureInfos['title'])
                ->setStart(date('d.m.Y', strtotime('tuesday this week')))
                ->setEnd(date('d.m.Y', strtotime('monday next week')))
                ->setLanguageCode($singleLanguage)
                ->setVariety('leaflet')
                ->setTags($aBrochureInfos['tags'])
                ->setBrochureNumber('CW' . $sTimes->getWeekNr() . '_' . $sTimes->getWeeksYear() . '_' . $singleLanguage);

            $aZipcode = $sTranslation->findZipcodesForLanguageCode($singleLanguage);

            $sStoreNumbers = '';
            foreach ($cStores->getElements() as $eStore) {
                if (in_array(trim($eStore->getZipcode()), $aZipcode)) {
                    if (strlen($sStoreNumbers)) {
                        $sStoreNumbers .= ',';
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
