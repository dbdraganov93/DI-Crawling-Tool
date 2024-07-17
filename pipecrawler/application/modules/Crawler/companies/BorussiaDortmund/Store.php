<?php

/*
 * Store Crawler für Borussia Dortmund (ID: 69894)
 */

class Crawler_Company_BorussiaDortmund_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://shop.bvb.de/';
        $searchUrl = $baseUrl . 'bvb-fanwelt-und-bvb-fanshops';
        $sPage = new Marktjagd_Service_Input_Page(true);
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match_all('#<div[^>]*style="margin-top:[^>]*20px;"[^>]*>(<img.*?)</div>\s*<p#is', $page, $matchStores)) {
            throw new Exception ($companyId . ': unable to get store list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($matchStores[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $patternImageText = '#<img[^>]*src="(.*?)"[^>]*>\s*</div>\s*<div[^>]*>(.*?)</div>#is';
            if (preg_match($patternImageText, $singleStore, $matchImageText)) {
                $eStore->setImage($baseUrl . $matchImageText[1]);
                $eStore->setText(preg_replace("#\n#"," " , $matchImageText[2]));
            }

            if (preg_match('#<span[^>]*>Adresse\s*und\s*Kontaktinfos</span>\s*<br>[^<]*<br>\s*([^<]*)\s*<br>\s*([^<]*)\s*<br>\s*([^<]*)\s*<#is', $singleStore, $matchAddress)) {
                $eStore->setStreetAndStreetNumber(trim($matchAddress[1]));
                $eStore->setZipcodeAndCity(trim($matchAddress[2]));

                if (substr(trim($matchAddress[3]), 0, 3) == "Tel") {
                    $eStore->setPhoneNormalized(trim($matchAddress[3]));
                } else {
                    $eStore->setStreetAndStreetNumber(trim($matchAddress[2]));
                    $eStore->setZipcodeAndCity(trim($matchAddress[3]));
                }
            }

            if (preg_match('#ffnungszeiten</span>(.*?)$#is', $singleStore, $matchOpenings)) {
                $eStore->setStoreHoursNormalized($matchOpenings[1]);
            }
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
