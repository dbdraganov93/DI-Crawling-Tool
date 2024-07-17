<?php

/*
 * Prospekt Crawler für Markant Markt (ID: 28979)
 */

class Crawler_Company_MarkantMarkt_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.mein-markant.de/';
        $searchUrl = $baseUrl . 'mein-markt/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);

        $cStores = $sApi->findStoresByCompany($companyId);
        $localFolder = $sHttp->generateLocalDownloadFolder($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cStores->getElements() as $eStore) {
            $ch = curl_init($searchUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Cookie: f6292da8fa04864903074c339447293c=' . $eStore->getStoreNumber()));
            $result = curl_exec($ch);

            $pattern = '#<a[^>]*href="\s*([^"]+?)"[^>]*>\s*Wochenangebote#';
            if (!preg_match($pattern, $result, $brochureUrlMatch)) {
                $this->_logger->info($companyId . 'not brochure for store number: ' . $eStore->getStoreNumber());
                continue;
            }

            $pattern = '#markant\.mycontent2\.eu#';
            if (preg_match($pattern, $brochureUrlMatch[1])) {
                $brochureUrl = $brochureUrlMatch[1] . 'ww.pdf';
            } else {
                $count = 1;
                $aFiles = array();
                mkdir($localFolder . $eStore->getStoreNumber() . '/', 0775, true);
                $storeFolder = $localFolder . $eStore->getStoreNumber() . '/';
                while (TRUE) {
                    $brochureUrl = $brochureUrlMatch[1] . 'pdf/100' . str_pad($count, 2, '0', STR_PAD_LEFT) . '.pdf';
                    if (!$sPage->checkUrlReachability($brochureUrl)) {
                        break;
                    }
                    $sHttp->getRemoteFile($brochureUrl, $storeFolder);
                    $aFiles[$count] = $storeFolder . '100' . str_pad($count, 2, '0', STR_PAD_LEFT) . '.pdf';
                    $count++;
                }
                $brochureUrl = $sCsv->generatePublicBrochurePath($sPdf->merge($aFiles, $localFolder));
            }
            
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Wochen Angebote')
                    ->setUrl($brochureUrl)
                    ->setStart(date('d.m.Y', strtotime('monday this week')))
                    ->setEnd(date('d.m.Y', strtotime('saturday this week')))
                    ->setVisibleStart($eBrochure->getStart())
                    ->setVariety('leaflet')
                    ->setStoreNumber($eStore->getStoreNumber());
            
            $cBrochures->addElement($eBrochure);
        }
        
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
