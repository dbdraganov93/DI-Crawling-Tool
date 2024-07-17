<?php

/**
 * Store Crawler für Nobis Printen (ID: 71317)
 */
class Crawler_Company_Nobis_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.nobis-printen.de/';
        $searchUrl = $baseUrl . 'baeckereien-cafes/aachen-innenstadt.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        if (!$sPage->open($searchUrl)) {
            throw new Exception($companyId . ': unable to open store overview page.');
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*(href="/baeckereien-cafes/aachen-innenstadt.html".+?)</ul#s';
        if (!preg_match($pattern, $page, $listMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#href="\/([^"]+)"#';
        if (!preg_match_all($pattern, $listMatch[1], $listSiteMatches)) {
            throw new Exception($companyId . ': unable to get any store list link.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($listSiteMatches[1] as $singleStoreList) {
            if (!$sPage->open($baseUrl . $singleStoreList)) {
                $this->_logger->err($companyId . ': unable to open store list page.');
            }

            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<a[^>]*name="(filiale.+?)</table>\s*</td>\s*</tr>\s*</table>\s*</td>\s*</tr>\s*</table>#s';
            if (!preg_match_all($pattern, $page, $storeDetailMatches)) {
                $this->_logger->info($companyId . ': no stores found for url: ' . $baseUrl . $singleStoreList);
                continue;
            }
            
            foreach ($storeDetailMatches[1] as $singleStore) {
                $eStore = new Marktjagd_Entity_Api_Store();
                
                $pattern = '#Zentrale\s*Ruf.+?<td[^>]*>([0-9]+\/{1}[0-9]+)<#';
                if (!preg_match($pattern, $singleStore, $telMatch)) {
                    continue;
                }
                
                $pattern = '#filiale([0-9]+)"#';
                if (!preg_match($pattern, $singleStore, $numberMatch)) {
                    $this->_logger->err($companyId . ': unable to get store number.');
                    continue;
                }
                
                $pattern = '#<p[^>]*class="location-address"[^>]*>(.+?)</p#';
                if (!preg_match($pattern, $singleStore, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address.');
                    continue;
                }
                
                $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);
                
                $pattern = '#Öffnungszeiten(.+?)<td[^>]*colspan#s';
                if (!preg_match($pattern, $singleStore, $timeMatch)) {
                    $this->_logger->info($companyId . ': unable to get store hours.');
                }
                
                $pattern = '#<img[^>]*src="\/(media\/locations\/[0-9]+\_quer.+?)"#';
                if (preg_match($pattern, $singleStore, $imageMatch)) {
                    $eStore->setImage($baseUrl . $imageMatch[1]);
                }
                
                $pattern = '#(Filial.+?)</td>\s*<td[^>]*>(.+?)<#';
                if (preg_match($pattern, $singleStore, $textMatch)) {
                    $eStore->setText($textMatch[1] . ' ' . $textMatch[2]);
                }
                
                $pattern = '#templatedata\/typo\_(.+?)\.gif#';
                if (preg_match($pattern, $singleStore, $typeMatch)) {
                    switch ($typeMatch[1]) {
                        case 'cafe': {
                            $eStore->setSubtitle('Backwaren & Printen Café');
                            break;
                        }
                        case 'specials': {
                            $eStore->setSubtitle('Printen Spezialgeschäft');
                            break;
                        }
                        case 'bakery': {
                            $eStore->setSubtitle('Backwaren & Printen');
                            break;
                        }
                        case 'take-away': {
                            $eStore->setSubtitle('Backwaren & Printen Stehcafé');
                            break;
                        }
                        default: {
                            $this->_logger->err($companyId . ' unknown category.');
                        }
                    }
                }
                
                $eStore->setStoreNumber($numberMatch[1])
                        ->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                        ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))
                        ->setCity($aAddress[1])
                        ->setStoreHours($sTimes->generateMjOpenings($timeMatch[1]));
                
                $cStores->addElement($eStore);
            }
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
