<?php
/**
 * Store Crawler für Expert (ID: 87)
 */

class Crawler_Company_Expert_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.expert.de/';
        $searchUrl = $baseUrl . '_api/storeFinder/searchStores';
        $storeJson = json_decode($this->curlJson($searchUrl));

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeJson as $singleStore) {

            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleStore->store->id)
                ->setStreetAndStreetNumber($singleStore->store->street)
                ->setZipcode($singleStore->store->zip)
                ->setCity($singleStore->store->city)
                ->setStoreHoursNormalized($this->hours($singleStore->openingTimes))
                ->setPhoneNormalized($singleStore->store->phone)
                ->setLatitude($singleStore->store->latitude)
                ->setLongitude($singleStore->store->longitude);


            $cStores->addElement($eStore);

        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

    private function curlJson($url)
    {
        $data = array("autoComplete" => false,
            "search" => "01328 Dresden, Germany",
            "maxResults" => 100000,
            "conditions" => array("storeFinderResultFilter" => "ALL"));

        $postData = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function hours($timeData)
    {
        $hourString = '';
        foreach ($timeData as $key => $value) {
            foreach ($value as $openingHours) {
                if (is_array($openingHours)) {
                    foreach ($openingHours as $openingTime) {
                        $hourString .= $key . ': ' . $openingTime . ',';
                    }
                }
            }

            return $hourString;
        }
    }
}