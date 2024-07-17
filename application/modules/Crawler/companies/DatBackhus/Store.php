<?php

/**
 * Class Crawler_Company_DatBackhus_Store
 *
 * Storecrawler für Dat Backhus (ID: 68935)
 */
class Crawler_Company_DatBackhus_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $cStores = new Marktjagd_Collection_Api_Store();
        $baseUrl = 'http://www.datbackhus.de/';
        $searchUrl = $baseUrl . '_include/ajax.asp?LatLng=53.5510846%7C9.99368179999999&intDistance=1000&txtView=map';
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($searchUrl);

        $json = $sPage->getPage()->getResponseAsJson();

        foreach ($json->results as $data) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle('Dat Backhus ' . $data->name);
            $eStore->setLatitude($data->lat);
            $eStore->setLongitude($data->lng);

            $fulladdress = $data->descript;
            $pattern = "#<b>\s*?<a.*href='(.*?)'>.*?<\/a>\s*?<\/b>#";

            if (preg_match($pattern, $fulladdress, $match)) {
                $eStore->setWebsite($baseUrl . preg_replace('#^/#', '', $match[1]));
            }

            $pattern = '#<\/div>(.*?)Entfernung:#';
            if (!preg_match($pattern, $fulladdress, $match)) {
                $this->_logger->err('no street+no zip+city found');
                continue;
            }

            $t = preg_split('#<br\/>\s*?<br\/>#', $match[1]);
            $storeZipCity = strip_tags($t[1]);

            if (!preg_match('#>#', $t[0])) {
                $storeStreetNo = trim($t[0]);
            } else {
                $storeStreetNo = substr($t[0], strpos($t[0], '>') + 1);
                $storeSubtitleExt = substr($t[0], 0, strpos($t[0], '<'));
                if ($storeSubtitleExt != '') {
                    $eStore->setSubtitle($storeSubtitleExt);
                }
            }

            $eStore->setStreetAndStreetNumber($storeStreetNo);
            $eStore->setZipcodeAndCity($storeZipCity);

            // Öffnungszeiten
            $pattern = '#<table.*?>(.*?)</table>#';
            if (preg_match($pattern, $fulladdress, $match)) {
                $eStore->setStoreHoursNormalized($match[1]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}
