<?php

/* 
 * Store Crawler für Sport Tiedje (ID: 67386)
 */

class Crawler_Company_SportTiedje_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'http://www.sport-tiedje.de';
        $searchUrl = $baseUrl . '/de/UEbersicht-der-Filialen-141';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        $pattern = '#store:\s*\$\.parseJSON\(\(\'(.+?)\'#s';
        if (!preg_match($pattern, $page, $storeJsonMatch))
        {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (json_decode($storeJsonMatch[1]) as $country => $cities)
        {
            if (!preg_match('#de#', $country))
            {
                continue;
            }
            
            foreach ($cities as $singleJStore)
            {
                $strTimes = '';
                foreach ($singleJStore->oeffnungszeiten as $singleDay => $singleTimes)
                {
                    if (strlen($strTimes))
                    {
                        $strTimes .= ',';
                    }
                    $strTimes .= $singleDay . ' ' . implode('-', $singleTimes);
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setAddress($singleJStore->strasse, $singleJStore->plzort)
                        ->setPhoneNormalized($singleJStore->telefon)
                        ->setFaxNormalized($singleJStore->fax)
                        ->setEmail($singleJStore->email)
                        ->setStoreNumber($singleJStore->filialId)
                        ->setLatitude($singleJStore->breitengrad)
                        ->setLongitude($singleJStore->laengengrad)
                        ->setStoreHoursNormalized($strTimes)
                        ->setWebsite($baseUrl . $singleJStore->url);
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}