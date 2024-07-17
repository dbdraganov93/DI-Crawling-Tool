<?php

/**
 * Store Crawler für BB Bank (ID: 71660)
 */
class Crawler_Company_BBBank_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://fif.module.vr-networld.de/';
        $searchUrl = $baseUrl . 'filialfinderjq/services/vrdive.htm';
        $sPage = new Marktjagd_Service_Input_Page();

        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array(
            'remote_function' => 'banks',
            'api_radius' => '1000',
            'api_limit' => '1000',
            'bank_code' => '66090800'
        );
        
        $sPage->open($searchUrl, $aParams);
        $jStores = $sPage->getPage()->getResponseAsJson();
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores as $singleJStore) {
            if (!preg_match('#true_facility#', $singleJStore->facility_type)) {
                continue;
            }
            
            $strTimes = '';
            foreach ($singleJStore as $key => $value) {
                if (preg_match('#opening_time#', $key)) {
                    if (strlen($strTimes)) {
                        $strTimes .= ',';
                    }
                    $strTimes .= $value;
                }
            }
            
            $strNotes = '';
            foreach ($singleJStore as $key => $value) {
                if (preg_match('#info_text#', $key)) {
                    if (strlen($strNotes)) {
                        $strNotes .= '<br/>';
                    }
                    $strNotes .= $value;
                }
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setStoreNumber($singleJStore->id)
                    ->setPhoneNormalized($singleJStore->phone_area . $singleJStore->phone_number)
                    ->setFaxNormalized($singleJStore->fax_area . $singleJStore->fax_number)
                    ->setEmail($singleJStore->email)
                    ->setCity($singleJStore->city)
                    ->setZipcode($singleJStore->zip_code)
                    ->setStreetAndStreetNumber($singleJStore->street)
                    ->setWebsite($singleJStore->detail_page_url)
                    ->setLatitude($singleJStore->latitude)
                    ->setLongitude($singleJStore->longitude)
                    ->setStoreHoursNormalized($strTimes)
                    ->setStoreHoursNotes($strNotes);
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
