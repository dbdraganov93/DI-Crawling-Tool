<?php

/**
 * Store Crawler für K&L Ruppert (ID: 67534)
 */
class Crawler_Company_KundLRuppert_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     *
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.kl-ruppert.de';
        $searchUrl = $baseUrl . '/Filialfinder/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store list page.');
        }

        $page = $sPage->getPage()->getResponseBody();
        
        if (!preg_match('#<script[^>]*id="storefinder__data"[^>]*>(.+?)</script>#is', $page, $match)){
            throw new Exception('cannot find json data on page: ' . $searchUrl);
        }
                
        $json = json_decode(trim($match[1]));

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($json as $dataItem) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber((string) $dataItem->id)
                    ->setPhoneNormalized((string) $dataItem->fon)
                    ->setStreetAndStreetNumber((string) $dataItem->address->street)
                    ->setZipcode((string) $dataItem->address->zip)
                    ->setCity((string) $dataItem->address->city)
                    ->setLatitude((string) $dataItem->coords->lat)
                    ->setLongitude((string) $dataItem->coords->lng)
                    ->setWebsite((string) $dataItem->link)
                    ->setText((string) $dataItem->news);
                     
            $hoursStr = '';
            $hoursTextStr = '';
            foreach ($dataItem->openings as $opening){
                if ($opening->title == 'special'){
                    $hoursTextStr = $opening->value; 
                } else {
                    $hoursStr .= ',' .  $opening->title . ' ' . $opening->value->{0} . '-' . $opening->value->{1};
                }
            }
            
            $eStore->setStoreHoursNormalized($hoursStr);            
                     
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
