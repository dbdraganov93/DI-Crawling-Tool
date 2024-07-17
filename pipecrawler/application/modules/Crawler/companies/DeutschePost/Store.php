<?php

/**
 * Store Crawler für Deutsche Post (ID: 71630)
 */
class Crawler_Company_DeutschePost_Store extends Crawler_Generic_Company {

    private $cStores, $baseUrl, $sPage;
    
    public function crawl($companyId) {
        ini_set('memory_limit', '1G');
        
        $this->baseUrl = 'https://m.deutschepost.de';
        $searchUrl = $this->baseUrl . '/;lat;lng/vi/pf';
                
        // da nicht alle Standorte mit einem Lauf erfasst werden können, erfolgt das crawling über 7 Tage
        // jeden Tag wird ein anderer PLZ-Bereich gecrawlt (erste Ziffer),
        // da 10 Bereichen auf 7 Tage aufgeteilt werden, werden diese in etwa anzahlmäßig gleichverteilt
                
        $crawlDays = array ( 0 => array(0,5),
                             1 => array(1,6),                             
                             2 => array(2),
                             3 => array(3,4),
                             4 => array(7),
                             5 => array(8),
                             6 => array(9)
                            );
               
        $crawlDay = date('w');
        
        $this->sPage = new Marktjagd_Service_Input_Page();
        $this->cStores = new Marktjagd_Collection_Api_Store();
        
        $sDb = new Marktjagd_Database_Service_GeoRegion();       
        $aZipCodes = $sDb->findAllZipCodes();
        
        // vor dem Import werden alle bereits vorhandenen Standorte, außer dem aktuell zu erfassenden Bereich "gesichert"
        // und wieder mit importiert, so bleiben die Daten aktuell
        $sUnvStores = new Marktjagd_Service_Input_MarktjagdApi();
            
        $this->_logger->info('get stores from KERN');
        $cUnvStores = $sUnvStores->findStoresByCompany($companyId);
        $cUnvStores = $cUnvStores->getElements();
              
         /* @var $eUnvStore Marktjagd_Entity_Api_Store */
        foreach ($cUnvStores as $eUnvStore){
            if (!in_array(substr($eUnvStore->getZipcode(),0,1), $crawlDays[$crawlDay])){
                $this->cStores->addElement($eUnvStore);            
            }
        }
                
        $countZips = count($aZipCodes);
        $i = 0;        
        foreach ($aZipCodes as $zipCode) {
            $i++;
            // falls PLZ-Bereich nicht dran, dann überpsringen
            if (!in_array(substr($zipCode,0,1), $crawlDays[$crawlDay])){
                $this->_logger->info('skip ' . $zipCode . ' not today ...');
                continue;        
            }
            
            $this->_logger->info('crawl ' . $zipCode . ' ' . $i . ' of '. $countZips);
            
            $oPage = $this->sPage->getPage();
            $oPage->setMethod('POST');
            $oPage->setUseCookies(true);
            $this->sPage->setPage($oPage);

            $aParams = array(
                'lang' => 'de',
                'lat' => '',
                'lng' => '',
                'query' => 'postfinder',
                'query_type' => 'filialen_verkaufspunkte',
                'street' => '',
                'streetn_nr' => '',
                'suchenBtn' => 'Suchen',                
                'sv-method' => 'post',                
                'zip' =>	$zipCode
            );
        
            $this->sPage->open($searchUrl, $aParams);
            $page = $this->sPage->getPage()->getResponseBody();

            if (preg_match('#errorField#', $page)){
                if (preg_match_all('#<div[^>]*class="address small"[^>]*>\s*<a[^>]*href="([^"]+)">#', $page, $addressLinks)){
                    foreach ($addressLinks[1] as $addressLink){
                        $this->sPage->open($this->baseUrl . $addressLink);
                        $page = $this->sPage->getPage()->getResponseBody();
                        $this->_logger->info('get stores from sublinks');
                        $this->getStores($page);
                    }
                } 
            } else {
                $this->_logger->info('get stores from direct links');
                $this->getStores($page);
            }
        }    

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($this->cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
    
    private function getStores($page){        
        $sAddress = new Marktjagd_Service_Text_Address();        
        $sTimes = new Marktjagd_Service_Text_Times();
        
        if (preg_match_all('#<a[^>]*href="([^"]+)">\s*Infos\s*</a>#', $page, $infoLinks)){
            foreach($infoLinks[1] as $infoLink){
                try{
                    $this->sPage->open($this->baseUrl . $infoLink);
                } catch (Exception $ex){
                    $this->_logger->err('error while open: ' . $this->baseUrl . $infoLink);
                    continue;
                }
                
                $infoPage = $this->sPage->getPage()->getResponseBody();
                
                if (!preg_match('#<section>(.+?)</section>#', $infoPage, $addressSection)){
                    throw new Exception ('company: 71630 (DPAG) cannot get address info from info page');
                }
                
                $eStore = new Marktjagd_Entity_Api_Store();
                
                if (preg_match('#<div[^>]*class="headerText"[^>]*>([^<]+)</div>#', $infoPage, $headerMatch)){
                    $eStore->setTitle($headerMatch[1]);
                }
                
                if (preg_match('#<div[^>]*class="floatleft"[^>]*>\s*<b>\s*([^<]+)<#', $addressSection[1], $addressMatch)){
                    $addressLines = explode(',', $addressMatch[1]);                    
                    $eStore->setStreet($sAddress->extractAddressPart('street', $addressLines[0]))
                            ->setStreetNumber($sAddress->extractAddressPart('street_number', $addressLines[0]))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $addressLines[1]))
                            ->setCity($sAddress->extractAddressPart('city', $addressLines[1]));
                }
                
                if (preg_match('#<div[^>]*class="greybg"[^>]*>(.+?)</div>\s*</div>#', $addressSection[1], $hoursMatch)){
                    $eStore->setStoreHours($sTimes->generateMjOpenings($hoursMatch[1]));
                }
                
                if (preg_match('#<h2[^>]*class="detailhead">Leistungen</h2>(.+?)<br>\s*<br>#', $addressSection[1], $serviceMatch)){
                    $eStore->setService(preg_replace('#<br[^>]*>#', ', ', $serviceMatch[1]));
                }
                
                if (preg_match('#<h2[^>]*class="detailhead">Informationen</h2>(.+?)<br>\s*<br>#', $addressSection[1], $informationMatch)){
                    $informationLines = preg_split('#<br[^>]*>#', $informationMatch[1]);                    
                    foreach ($informationLines as $infoLine){
                        if (preg_match('#parkplatz#i', $infoLine)){
                            $eStore->setParking($infoLine);
                        } elseif (preg_match('#behindert|barriere#i', $infoLine)){
                            $eStore->setBarrierFree($infoLine);
                        } else {
                            $eStore->setText($eStore->getText() . $infoLine . '<br />');
                        }
                    }                    
                }

                if (preg_match('#<h2[^>]*class="detailhead">SB-Einrichtungen</h2>(.+?)<br>\s*<br>#', $addressSection[1], $sbMatch)){
                    if (!preg_match('#keine#', $sbMatch)){
                        $eStore->setText($eStore->getText() . '<br />SB-Einrichtungen<br />' . $sbMatch[1]);
                    }
                }
                
                $this->cStores->addElement($eStore);
            }
        }                     
    }
}
