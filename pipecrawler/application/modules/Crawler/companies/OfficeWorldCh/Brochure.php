<?php

/*
 * Prospekt Crawler für Office World CH (ID: 72226)
 */

class Crawler_Company_OfficeWorldCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.officeworld.ch/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();

        $cStores = $sApi->findStoresByCompany($companyId);

        $oPage = $sPage->getPage();
        $oPage->setUseCookies(TRUE);
        $sPage->setPage($oPage);

        $aTitle = array(
            'de' => array(
                'title' => 'Büro Angebote',
                'validity' => '#gültig\s*vom\s*([^\s]+?)\s*bis\s*([^,]+?)\s*,#'),
            'fr' => array(
                'title' => 'Offres de bureau',
                'validity' => '#Offres\s*valables\s*du\s*([^\s]+?)\s*au\s*([^,]+?)\s*,#')
        );

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="stage-banner"[^>]*href="\/(kampagnen[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $campaignUrlMatches)) {
            throw new Exception($companyId . ': no campaigns found.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $strStart = '';
        $strEnd = '';
        foreach ($campaignUrlMatches[1] as $singleCampaignUrl) {
            foreach ($aTitle as $singleLanguage => $aSingleInfo) {
                $campaignUrl = $baseUrl . preg_replace('#(sprache=).+#', '$1' . $singleLanguage, $singleCampaignUrl);

                $sPage->open($campaignUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*[^<]*(Jetzt\s*durchblättern|Feuilleter)#i';
                {
                    if (!preg_match($pattern, $page, $brochureInfoUrlMatch)) {
                        $this->_logger->info($companyId . ': no brochure found: ' . $campaignUrl);
                        continue;
                    }
                }

                $ch = curl_init($brochureInfoUrlMatch[1]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $page = curl_exec($ch);
                curl_close($ch);

                $pattern = '#pdf_txt\.replace\("\{1\}",\s*"([^"]+?\.pdf)"#';
                if (!preg_match($pattern, $page, $pdfUrlMatch)) {
                    $this->_logger->info($companyId . ': no pdf url found: ' . $brochureInfoUrlMatch[1]);
                    continue;
                }

                $brochureUrl = preg_replace('#(.+\/)([^\/]+)$#', '$1' . $pdfUrlMatch[1], $brochureInfoUrlMatch[1]);

                $sHttp = new Marktjagd_Service_Transfer_Http();
                $sPdf = new Marktjagd_Service_Output_Pdf();
                $localPath = $sHttp->generateLocalDownloadFolder($companyId);

                $localBrochurePath = $sHttp->getRemoteFile($brochureUrl, $localPath);
                $strText = $sPdf->extractText($localBrochurePath);

                if (!preg_match($aSingleInfo['validity'], $strText, $validityMatch)) {
                    throw new Exception($companyId . ': unable to get brochure validity.');
                }

                $strStart = $validityMatch[1];
                $strEnd = $validityMatch[2];

                if (!preg_match('#\.$#', $strStart)) {
                    $strStart .= '.';
                }

                if (!preg_match('#\d{4}$#', $strStart)) {
                    $strStart .= $sTimes->getWeeksYear();
                }

                $brochureUrl = $sHttp->generatePublicHttpUrl($localBrochurePath);

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setTitle('Highlights')
                    ->setUrl($brochureUrl)
                    ->setLanguageCode($singleLanguage)
                    ->setVariety('leaflet')
                    ->setStart($strStart)
                    ->setEnd($strEnd);

                $aZipcode = $sTranslation->findZipcodesForLanguageCode($singleLanguage);

                $sStoreNumbers = '';
                /* @var $eStore Marktjagd_Entity_Api_Store */
                foreach ($cStores->getElements() as $eStore) {
                    if (in_array(trim($eStore->getZipcode()), $aZipcode)) {
                        if (strlen($sStoreNumbers)) {
                            $sStoreNumbers .= ',';
                        }

                        $sStoreNumbers .= $eStore->getStoreNumber();
                    }
                }

                $eBrochure->setStoreNumber($sStoreNumbers)
                    ->setBrochureNumber(substr(md5($eBrochure->getStart() . $eBrochure->getEnd() . $eBrochure->getStoreNumber()), 0, 15));

                $cBrochures->addElement($eBrochure);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
