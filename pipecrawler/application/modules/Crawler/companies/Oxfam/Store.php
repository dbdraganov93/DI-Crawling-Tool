<?php

/**
 * Store Crawler für Oxfam (ID: 71359)
 */
class Crawler_Company_Oxfam_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://shops.oxfam.de';
        $searchUrl = $baseUrl . '/shops';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();       
        
        if (!preg_match_all('#<span[^>]*class="field-content"[^>]*>\s*<a[^>]*href="(/shops/[^"]+)">#', $page, $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleLink) {
            $storeUrl = $baseUrl . $singleLink;
            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();
           
            $eStore = new Marktjagd_Entity_Api_Store();
            
            $eStore->setWebsite($storeUrl);
            
            if (preg_match('#<div[^>]*>.*?ffnungszeiten.*?</div>\s*<div[^>]*>\s*<div[^>]*>\s*<p>(.+?)</p>#', $page, $match)){
                $eStore->setStoreHoursNormalized($match[1]);                      
            }
            
            if (preg_match('#<div[^>]*class="thoroughfare"[^>]*>(.+?)</div>#', $page, $match)){
                $eStore->setStreetAndStreetNumber($match[1]);                      
            }
            
            if (preg_match('#<span[^>]*class="postal-code"[^>]*>(.+?)</span>#', $page, $match)){
                $eStore->setZipcode($match[1]);                      
            }
            
            if (preg_match('#<span[^>]*class="locality"[^>]*>(.+?)</span>#', $page, $match)){
                $eStore->setCity($match[1]);                      
            }
            
            if (preg_match('#<div[^>]*>\s*tel.*?</div>\s*<div[^>]*>\s*<div[^>]*>(.+?)</div>#is', $page, $match)){
                $eStore->setPhoneNormalized($match[1]);                 
            }
            
            if (preg_match('#<div[^>]*>\s*fax.*?</div>\s*<div[^>]*>\s*<div[^>]*>(.+?)</div>#is', $page, $match)){
                $eStore->setFaxNormalized($match[1]);                 
            }
            
            if (preg_match('#<div[^>]*>(\s*nahverkehr.*?)</div>\s*<div[^>]*>\s*<div[^>]*>(.+?)</div>#is', $page, $match)){
                $eStore->setText(strip_tags($match[1]) . ': ' . strip_tags($match[2]) . '<br />' . '<br />');
            }
            
            if (preg_match('#<div[^>]*>(\s*weiteres.*?)</div>\s*<div[^>]*>\s*<div[^>]*>(.+?)</div>#is', $page, $match)){
                $eStore->setText($eStore->getText() . strip_tags($match[1]) . ': ' . strip_tags($match[2]) . '<br />' . '<br />');
            }
            
            if (preg_match('#<span>Sortiment</span>(.+?)</div>\s*</div>\s*</div>\s*#', $page, $match)){
                if (preg_match_all('#<span[^>]*class="field-content"[^>]*>(.+?)</span>#', $match[1], $submatch)){
                    $eStore->setSection(strip_tags(implode(',', $submatch[1])));
                }
                
                $eStore->setSection(preg_replace('#^\,#', '', $eStore->getSection()));
                $eStore->setSection(preg_replace('#\,\,#', ',', $eStore->getSection()));
            }
                        
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
