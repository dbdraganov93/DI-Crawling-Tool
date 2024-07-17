<?php

class Crawler_Company_Mediamarkt_BrochureWeb extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPage = new Marktjagd_Service_Input_Page();
//        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sHttp = new Marktjagd_Service_Transfer_Http();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        /** @var Marktjagd_Entity_Api_Store $eStore */
        foreach ($sApi->findStoresByCompany($companyId)->getElements() as $eStore) {
            $url = $eStore->getWebsite();
            if (substr($url, -1, 1) != '/') {
                $url .= '/';
            }
            $url .= 'angebote';
            try {
                $sPage->open($url);
                $page = $sPage->getPage()->getResponseBody();
                $pattern = '#<a[^>]*class="[^"]*download[^"]*"[^>]*href="([^"]+eflyer\.redblue[^"]+)"#';
                if (!preg_match_all($pattern, $page, $downloadMatches)) {
                    $this->_logger->info('no brochure available for ' . $url);
                    continue;
                }
                foreach ($downloadMatches[1] as $singleMatch) {
                    $localBrochure = $sHttp->getRemoteFile($singleMatch, $localPath);

                    if (!strlen($localBrochure)) {
                        continue;
                    }

//                    $localBrochure = $sPdf->implementSurvey($localBrochure, 3);

                    $eBrochure = new Marktjagd_Entity_Api_Brochure();
                    $eBrochure->setUrl($sHttp->generatePublicHttpUrl($localBrochure))
                        ->setBrochureNumber(substr(md5($singleMatch), 0, 25))
                        ->setVariety('leaflet')
                        ->setStoreNumber($eStore->getStoreNumber())
                        ->setTitle('Multimediaangebote');

                    if (preg_match('#<span[^>]*>Angebote.*?g.*?ltig\s*bis\s*([^<]+)#is', $page, $matchEndDate)) {
                        $eBrochure->setEnd($matchEndDate[1]);
                    }

                    $cBrochures->addElement($eBrochure);
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return $this->getResponse($cBrochures, $companyId, 0);
    }
}
