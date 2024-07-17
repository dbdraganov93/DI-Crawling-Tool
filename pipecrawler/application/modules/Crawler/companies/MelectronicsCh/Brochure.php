<?php
/**
 * Brochure Crawler für melectronics CH (ID: 72163)
 */

class Crawler_Company_MelectronicsCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.melectronics.ch/';
        $searchUrl = $baseUrl . '[[LANGUAGE]]/cp/aktionen';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sFtp->connect($companyId);
        $localPath = $sFtp->generateLocalDownloadFolder($companyId);

        $aLocalSurveyPaths = array();
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Umfrage[^\.]*_([A-Z]{2})\.pdf#', $singleFile, $localCodeMatch)) {
                $aLocalSurveyPaths[strtolower($localCodeMatch[1])] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $aLanguage = array(
            'de' => array('title' => 'Unser aktueller Prospekt',
                'patternValidity' => '#gelten\s*vom\s*([^\s]+?)\s*bis\s*([^\s]+)\s*#i'),
            'fr' => array('title' => 'Notre prospectus actuel',
                'patternValidity' => '#sont\s*valables\s*du\s*([^\s]+?)\s*au\s*([^\s]+)\s*#i'),
            'it' => array('title' => 'Il nostro prospetto attuale',
                'patternValidity' => '#sono\s*valide\s*dal\s*([^\s]+?)\s*al\s*([^\s]+)\s*#i')
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguage as $languageCode => $aLanguageInfos) {
            $singleUrl = preg_replace('#\[\[LANGUAGE\]\]#', $languageCode, $searchUrl);

            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="(https?:\/\/www\.1kcloud\.com\/([^\/]+?)\/)"#';
            if (!preg_match($pattern, $page, $brochureInfoUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure info url.');
                continue;
            }

            $brochureUrl = $brochureInfoUrlMatch[1] . 'epaper/ausgabe.pdf';

            $localBrochurePath = $sHttp->getRemoteFile($brochureUrl, $localPath);

            $pdfText = $sPdf->extractText($localBrochurePath);

            if (!preg_match($aLanguageInfos['patternValidity'], $pdfText, $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity.');
                continue;
            }

            if (!preg_match('#\.$#', $validityMatch[1])) {
                $validityMatch[1] .= '.';
            }

            if (!preg_match('#\d{4}$#', $validityMatch[1])) {
                $validityMatch[1] .= $sTimes->getWeeksYear();
            }

            if (array_key_exists($languageCode, $aLocalSurveyPaths)) {
                $localBrochurePath = $sPdf->insert($localBrochurePath, $aLocalSurveyPaths[$languageCode], 2);
            }

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

            $eBrochure->setUrl($sFtp->generatePublicFtpUrl($localBrochurePath))
                ->setTitle($aLanguageInfos['title'])
                ->setStart($validityMatch[1])
                ->setEnd($validityMatch[2])
                ->setVisibleStart($eBrochure->getStart())
                ->setVariety('leaflet')
                ->setStoreNumber($strStoreNumbers)
                ->setLanguageCode($languageCode)
                ->setBrochureNumber($brochureInfoUrlMatch[2]);

            $cBrochures->addElement($eBrochure);

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}