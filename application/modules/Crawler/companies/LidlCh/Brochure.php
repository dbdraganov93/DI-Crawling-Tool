<?php

/**
 * Brochure Crawler für Lidl CH (ID: 72148)
 */
class Crawler_Company_LidlCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://media.lidl-flyer.com/';
        $searchUrl = $baseUrl . 'overview/[[languageCode]]-CH.json';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $localPath = $sHttp->generateLocalDownloadFolder($companyId);
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cStores = $sApi->findStoresByCompany($companyId);

        $aLanguages = array(
            'de' => array(
                'searchPatternCategory' => '#werbeprospekte#i',
                'searchPatternBrochure' => '#lidl\s*aktuell\s*kw#i'
            ),
            'fr' => array(
                'searchPatternCategory' => '#prospectus#i',
                'searchPatternBrochure' => '#lidl\s*actuel\s*sem#i'
            ),
            'it' => array(
                'searchPatternCategory' => '#volantini#i',
                'searchPatternBrochure' => '#lidl\s*attuale\s*s#i'
            )
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguages as $languageCode => $aBrochureInfos) {
            $sPage->open(preg_replace('#\[\[languageCode\]\]#', $languageCode, $searchUrl));
            $jInfos = $sPage->getPage()->getResponseAsJson();

            foreach ($jInfos->categories as $singleJBrochureCategorie) {
                foreach ($singleJBrochureCategorie->subcategories as $singleJBrochureSubCategorie) {
                    if (!preg_match($aBrochureInfos['searchPatternCategory'], $singleJBrochureSubCategorie->name)) {
                        continue;
                    }
                    foreach ($singleJBrochureSubCategorie->flyers as $singleJBrochure) {
                        if (strtotime('now') > strtotime($singleJBrochure->endDate)
                            || !preg_match($aBrochureInfos['searchPatternBrochure'], $singleJBrochure->name)) {
                            continue;
                        }

                        $sHttp->getRemoteFile($singleJBrochure->pdfUrl, $localPath);
                        $localFileName = preg_replace('#.+\/([^\/]+)$#', '$1', $singleJBrochure->pdfUrl);
                        $pdfFile = $localPath . $localFileName;
                        $newName = $localPath . $singleJBrochure->id . '.pdf';
                        if (rename($pdfFile, $newName)) {
                            $pdfFile = $newName;
                        }
                        $clickoutLink = 'https://www.lidl.ch/[[languageCode]]/index.htm?utm_campaign=profital&utm_source=blaetterkatalog_kw[[calendar_week]]&utm_medium=link';
                        $clickoutLink = preg_replace('#\[\[languageCode\]\]#', $languageCode, $clickoutLink);
                        $clickoutLink = preg_replace('#\[\[calendar_week\]\]#', date('W', strtotime($singleJBrochure->endDate)), $clickoutLink);
                        $pageCount = $sPdf->getPageCount($pdfFile);
                        $jsonAnnotations = array();
                        for ($i = 0; $i < $pageCount; $i++) {
                            $jsonAnnotations[] = array(
                                'page' => $i,
                                'height' => 100,
                                'width' => 100,
                                'startX' => 45,
                                'startY' => 47,
                                'endX' => 55,
                                'endY' => 53,
                                'link' => $clickoutLink
                            );
                        }
                        $jsonFileContent = json_encode($jsonAnnotations);
                        file_put_contents($localPath . 'annotations.json', $jsonFileContent);
                        $localFile = $sPdf->setAnnotations($pdfFile, $localPath . 'annotations.json');

                        $eBrochure = new Marktjagd_Entity_Api_Brochure();

                        $eBrochure->setTitle($singleJBrochure->name)
                            ->setVisibleStart(date('d.m.Y', strtotime($singleJBrochure->endDate . '- 7 days')))
                            ->setStart(date('d.m.Y', strtotime($singleJBrochure->endDate . '- 6 days')))
                            ->setEnd($singleJBrochure->endDate)
                            ->setUrl($sHttp->generatePublicHttpUrl($localFile))
                            ->setVariety('leaflet')
                            ->setBrochureNumber(substr($singleJBrochure->id, 0, 20))
                            ->setLanguageCode($languageCode);

                        $aZipcode = $sTranslation->findZipcodesForLanguageCode($languageCode);

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
                }

            }

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
