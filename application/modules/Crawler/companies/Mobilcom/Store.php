<?php

/**
 * Store Crawler für MobilCom Debitel (ID: 28877)
 */
class Crawler_Company_Mobilcom_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.mobilcom-debitel.de/';
        $searchUrl = $baseUrl . 'online-shop/api.php?action=queryByPostal&postal=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aUrls = $sGen->generateUrl($searchUrl, 'zip');

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl){
            $sPage->open($singleUrl);
            $jStores = $sPage->getPage()->getResponseAsJson()->shops;
            if (!$jStores) {
                continue;
            }
            
            foreach ($jStores as $singleJStoreNumber => $singleJStore) {
                $aContact = preg_split('#\n#', $singleJStore->com);
                $aInfos = preg_split('#\s*<[^>]*>\s*#', $singleJStore->info);
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                foreach ($aInfos as $singleInfo) {
                    if (preg_match('#^http#', $singleInfo)) {
                        $eStore->setWebsite($singleInfo);
                        continue;
                    } elseif (strlen($singleInfo)) {
                        $eStore->setService($singleInfo);
                        continue;
                    }
                }
                
                $eStore->setStoreNumber($singleJStoreNumber)
                        ->setLongitude($singleJStore->longitude)
                        ->setLatitude($singleJStore->latitude)
                        ->setStreetAndStreetNumber($singleJStore->street)
                        ->setZipcode($singleJStore->postal)
                        ->setCity($singleJStore->city)
                        ->setEmail(preg_replace('#.+:\s*(.+)#', '$1', $aContact[2]))
                        ->setPhoneNormalized($aContact[0])
                        ->setFaxNormalized($aContact[1])
                        ->setStoreHoursNormalized($singleJStore->open);
                
                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
