<?php

/*
 * Store Crawler für Globus CH (ID: 72184)
 */

class Crawler_Company_GlobusCh_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.globus.ch/';
        $searchUrl = $baseUrl . 'filialsuche';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#__NEXT_DATA__\s*=\s*(\{.+?\})\s*;\s*__NEXT#';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $jStores = json_decode($storeListMatch[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($jStores->props->initialStoreState->stores->stores as $singleJStore) {
            if (!preg_match('#CH#', $singleJStore->address->countryCode)) {
                continue;
            }
            
            $strTimes = '';
            foreach ($singleJStore->facilities[0]->openingTimes as $singleDay) {
                if (strlen($strTimes)) {
                    $strTimes .= ',';
                }
                $strTimes .= $singleDay->localizedDay . ' ' . $singleDay->timePeriod->from . '-' . $singleDay->timePeriod->to;
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($singleJStore->storeID)
                    ->setPhoneNormalized($singleJStore->contact->phone)
                    ->setEmail($singleJStore->contact->email)
                    ->setStreetAndStreetNumber($singleJStore->address->street, 'CH')
                    ->setZipcode($singleJStore->address->zip)
                    ->setCity($singleJStore->address->city)
                    ->setLongitude($singleJStore->geo->loc->coordinates[0])
                    ->setLatitude($singleJStore->geo->loc->coordinates[1])
                    ->setStoreHoursNormalized($strTimes)
                    ->setWebsite($singleJStore->uri);
            
            if (strlen($eStore->getWebsite())) {
                $sPage->open($eStore->getWebsite());
                $page = $sPage->getPage()->getResponseBody();
                
                $pattern = '#SORTIMENT\s*</p>\s*<p[^>]*>(.+?)<br[^>]*>\s*</p#';
                if (preg_match($pattern, $page, $sectionListMatch)) {
                    $eStore->setSection($sectionListMatch[1]);
                }
                
                $pattern = '#Unsere\s*Services(.+?)</div>\s*</div>\s*</div>\s*</div>\s*</div>#';
                if (preg_match($pattern, $page, $serviceListMatch)) {
                    $pattern = '#<img[^>]*>\s*<div[^>]*>\s*([^<]+?)\s*</div#';
                    if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                        $eStore->setService(implode(', ', $serviceMatches[1]));
                    }
                }
                
                $pattern = '#<img[^>]*src="\/(neos\/image\/12\/[^"]+?)"#';
                if (preg_match($pattern, $page, $imageMatch)) {
                    $eStore->setImage($baseUrl . $imageMatch[1]);
                }
            }
            
            $cStores->addElement($eStore, TRUE);
        }
        
        return $this->getResponse($cStores, $companyId);
    }

}
