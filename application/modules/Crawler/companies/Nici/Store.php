<?php

/**
 * Store Crawler für Nici (ID: 68084)
 */
class Crawler_Company_Nici_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.nici.de/index.php?id=17';
        $searchUrl = $baseUrl . '&tx_tpnationaldealer_pi1[search]=true&tx_tpnationaldealer_pi1[bundesland]=[BL]&tx_tpnationaldealer_pi1[sucheinitialisiert]=ja&tx_tpnationaldealer_pi1[zip]=&tx_tpnationaldealer_pi1[city]=';
        
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page(true);
        $oPage = $sPage->getPage()->setAlwaysStripComments(true);
        $sPage->setPage($oPage);
        $sPage->open($baseUrl);
                
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match_all('#javascript:suche\(\'([^\']+)\'\)#', $page, $regions)){
            throw new Exception('cannot get regions from ' . $baseUrl);
        }


        foreach ($regions[1] as $region){
            $storeUrl = preg_replace('#\[BL\]#', $region, $searchUrl);
            $storeUrl = str_replace('ü', '%C3%BC',$storeUrl);
            $sPage->open($storeUrl);
            $page = preg_replace('#<!--.*?-->#m','',$sPage->getPage()->getResponseBody());

            $pattern = '#<tr[^>]*>\s*<td[^>]*>(.+?)</td>\s*<td[^>]*>(.+?)</td>#';
            if (!preg_match_all($pattern, $page, $dMatch)){
                $this->_logger->err('cannot match dealer entries for ' . $storeUrl);
                continue;
            }

            foreach ($dMatch[0] as $idx => $match){
                $addressHtml = $dMatch[1][$idx];
                $dealerHtml = $dMatch[2][$idx];
                $pattern = '#<strong>([^<]+)\s*<img[^>]*>\s*</strong>\s*<br[^>]*>(.+?)<br[^>]*>(.+?)$#';

                if (!preg_match($pattern, $addressHtml, $addrMatch)){
                    if (preg_match('#mc\s*paper#is', $addressHtml)) {
                        continue;
                    }


                    $this->_logger->err('cannot match address from ' . $addressHtml);
                    continue;
                }

                if (!preg_match('#alt="NICI-Shop"#', $addressHtml)){
                    continue;
                }

                if (!preg_match('#[^0-9]*([0-9]{4,5})\s+(.+)$#', $addrMatch[3], $cMatch)){
                    $this->_logger->err('cannot match zipcode and city from ' . $addrMatch[3]);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setTitle(preg_replace('#\s*&nbsp;\s*#', '', $addrMatch[1]));
                $eStore->setStreetAndStreetNumber($addrMatch[2]);
                $eStore->setZipcode(str_pad($cMatch[1], 5, '0'));
                $eStore->setCity($cMatch[2]);

                if (preg_match('#<span[^>]*class="hpContentDealer"[^>]*>\s*<a[^>]*href="mailto:([^"]+)"#', $dealerHtml, $mailMatch)){
                    $eStore->setEmail($mailMatch[1]);
                }

                if (preg_match('#Telefon:\s*<span[^>]*class="hpContentDealer"[^>]*>(.+?)</span>#', $dealerHtml, $phoneMatch)){
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                if (preg_match('#zeiten:<br[^>]*>\s*<span[^>]*class="hpContentDealer"[^>]*>(.+?)</span>#', $dealerHtml, $hoursMatch)){
                    $eStore->setStoreHoursNormalized($hoursMatch[1]);
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);        
    }
}
