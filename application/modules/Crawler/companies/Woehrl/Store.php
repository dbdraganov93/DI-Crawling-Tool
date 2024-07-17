<?php

/**
 * Storecrawler für Wöhrl (ID: 22243)
 */
class Crawler_Company_Woehrl_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.woehrl.de';
        $searchUrl = $baseUrl . '/allehaeuser.html';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();        
        
        $pattern = '#<a[^>]*href="(/allehaeuser.html\?path[^"]+)"#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (array_unique($storeMatches[1]) as $singleStore) {
            $storeUrl = $baseUrl . $singleStore;
            $eStore = new Marktjagd_Entity_Api_Store();

            $sPage->open($storeUrl);
            $page = $sPage->getPage()->getResponseBody();

            $eStore->setWebsite($storeUrl);
            
            if (preg_match('#<img[^>]*src="([^"]+keyvisual.jpg)"#', $page, $match)){
                $eStore->setImage($baseUrl . $match[1]);
            }            
            
            if (preg_match('#<em>Adresse</em>\s*</h5>\s*<p[^>]*>(.+?)<br>(.+?)</p>#', $page, $match)){
                $eStore->setStreetAndStreetNumber($match[1])
                        ->setZipcodeAndCity($match[2]);
            }
            
            if (preg_match('#href="tel:([^"]+)"#', $page, $match)){
                $eStore->setPhoneNormalized($match[1]);                        
            }
            
            if (preg_match('#<em>Fax</em>\s*</h5>\s*<p[^>]*>(.+?)</p>#', $page, $match)){
                $eStore->setFaxNormalized($match[1]);                        
            }
            
            if (preg_match('#href="mailto:([^"]+)"#', $page, $match)){
                $eStore->setEmail($match[1]);                        
            }
            
            if (preg_match('#<hr>\s*<h5>\s*<em>(.+?)</em>\s*</h5>#', $page, $match)){
                $eStore->setStoreHoursNormalized($match[1]);
            }

            if (preg_match_all('#<svg[^>]*class="check"[^>]*>.+?</svg>\s*(.+?)\s*</a>#', $page, $match)){
                $eStore->setService(implode(', ', $match[1]));
            }
            
            Zend_Debug::dump($eStore);            
            $cStores->addElement($eStore);
        }       
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
