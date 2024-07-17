<?php
	
/**
 * Store Crawler für Intertoys (ID: 68042)
 */
class Crawler_Company_Intertoys_Store extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'http://www.intertoys.de/';
        $searchUrl = $baseUrl . 'BlokkerStoreLocator'
                . '?catalogId=12552'
                . '&langId=-3'
                . '&orderId='
                . '&storeId=10154'
                . '&assortment='
                . '&country=DE'
                . '&fromPage=StoreLocator'
                . '&objectId='
                . '&radius=500'
                . '&requesttype=ajax'
                . '&searchTerm=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&special_sunday=false'
                . '&special_value=';
            
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();
        
        $aLinks = $sGen->generateUrl($searchUrl, 'zip', 100);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $storeCache = array();
        foreach ($aLinks as $singleLink) {
            if (!$sPage->open($singleLink)) {
                throw new Exception ($companyId . ': unable to open store list page. url: ' . $singleLink);
            }
            
            $page = $sPage->getPage()->getResponseBody();
            
            if (!preg_match_all('#<detailsurl>([^<]+)</detailsurl>#', $page, $detailLinkMatches)){
                continue;
            }
            
            foreach ($detailLinkMatches[1] as $detailLink){
                $storeId = '';
                
                if (preg_match('#physicalStoreId=([0-9]+)[^0-9]#', $detailLink, $storeIdMatch)){
                    $storeId = $storeIdMatch[1];
                }
                                
                if (in_array($storeId, $storeCache) && strlen($storeId)){
                    continue;
                }                                
                
                $sPage->open($detailLink);
                $page = $sPage->getPage()->getResponseBody();                                                
                
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setWebsite($detailLink)
                        ->setStoreNumber($storeId);                                                  
                
                if (preg_match('#var\s*lat\s*=\s*([^\;]+)\;#is', $page, $latMatch)){
                    $eStore->setLatitude(trim($latMatch[1]));                   
                }
                
                if (preg_match('#var\s*lon\s*=\s*([^\;]+)\;#is', $page, $lonMatch)){
                    $eStore->setLongitude(trim($latMatch[1]));                   
                }                
                
                if (preg_match('#<div[^>]*class="store_photo">.*?<img[^>]*src="([^"]+)"#is', $page, $imageMatch)){
                    $eStore->setImage($baseUrl . $imageMatch[1]);                            
                }                     
                
                if (preg_match('#<div[^>]*class="store_header">([^<]+)</div>#', $page, $headerMatch)){                    
                    $eStore->setSubtitle(trim($headerMatch[1]));                    
                }                
                
                if (preg_match('#<div[^>]*class="store_adrress">(.+?)</div>#', $page, $addressMatch)){                    
                    $addressLines = preg_split('#<[^>]*>#', $addressMatch[1]);
                    
                    $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[3]))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[3]))
                            ->setCity($sAddress->extractAddressPart('city', str_replace(', ', '', $addressLines[5])))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[5]))
                            ->setPhone($sAddress->normalizePhoneNumber($addressLines[7]));
                }                                                                
                
                if (preg_match('#<span class="store_opening_header">.+?</span>\s*<div[^>]*>\s*(<table[^>]+>.+?</table>)#', $page, $hoursMatch)){
                    $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
                }
                
                if (preg_match('#<span[^>]*>Extra Öffnungszeiten</span>(.+?)</div>\s*</div>#', $page, $hoursExtraMatch)){
                    $hoursExtra = $hoursExtraMatch[1];
                    $hoursExtra = preg_replace('#</div>\s*<div class="data">#', ' ', $hoursExtra);
                    $hoursExtra = preg_replace('#</div>\s*<div class="labels">#', ', ', $hoursExtra);
                    $hoursExtra = preg_replace('#<[^>]*>#', '', $hoursExtra);
                                        
                    $eStore->setStoreHoursNotes(trim($hoursExtra));                    
                }
                                                
                $cStores->addElement($eStore);                
                $storeCache[] = $storeId;
            }            
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}