<?php

class Crawler_Company_Kaufland_Article extends Crawler_Generic_Company {
  
    private $sPage, $cArticle, $priceMatch, $priceReplace, $baseUrl, $articleCache, $storeUrls;    
    
    public function setStoreUrl($urls) {
        $this->storeUrls = $urls;
    }
    
    public function crawl($companyId) {
        $this->baseUrl = 'http://kaufland.de';
        
        if (!is_array($this->storeUrls)){
            $this->setStoreUrl(array('http://kaufland.de/Home/01_Angebote/Aktuelle_Woche/index.jsp'));        
        }
            
        // wenn leer, dann werden Unterkategorien erfasst
        $crawlSubCategory = '';
        //$crawlSubCategory = '#02_Obst|03_Molkereiprodukte#';                
        
        $this->priceMatch = array ('#[A-Za-z]#',
                            '#\s+#',                            
                            '#\.#',
                            '#-,#',
                            '#,-#',
                            '#,,#'
                            );
        
        $this->priceReplace = array('',
                            '',                            
                            ',',
                            '0,',
                            '',
                            '');
                
        $this->sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sText = new Marktjagd_Service_Text_TextFormat();
        
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aStores = $sApi->findStoresByCompany($companyId);
        
        $this->cArticle = new Marktjagd_Collection_Api_Article();
        $this->articleCache = array();                
        
        $page = $this->sPage->getPage();
        $page->setUseCookies(true);
        $this->sPage->setPage($page);
        
        // Standortnummern aus KERN holen
        $storeNumbersFromUNV = array();        
        foreach ($aStores->getElements() as $singleStore) {
            $storeNumbersFromUNV[] = $singleStore->storeNumber;
        }                    
        
        $storeCount = count($storeNumbersFromUNV);
        $storeCounter = 0;
        
        foreach ($storeNumbersFromUNV as $storeId){
            $this->_logger->info('crawl store ' . ++$storeCounter . ' of ' . $storeCount);
            foreach ($this->storeUrls as $storeUrl){
                $startUrl = $storeUrl . '?FilialID=' . $storeId;
                
                try {
                    $this->sPage->open($startUrl);
                } catch (Exception $ex) {
                    $this->_logger->err($companyId . ': error while open ' . $startUrl);
                    continue;
                }
                
                $page = $this->sPage->getPage()->getResponseBody();
                
                if (!preg_match('#<li[^>]*class="[^"]*leftnavi-level-1-active[^"]*"[^>]*>.*?<ul[^>]*>(.+?)</ul>#', $page, $matchSubLinks)){
                    $this->crawlArticleCategory($storeUrl, $storeId);
                    continue;
                }    
                    
                if (!preg_match_all('#<a[^>]*href="([^"]+)"#', $matchSubLinks[1], $matchSubLinkUrl)){
                    $this->_logger->err("company: " . $companyId . " cannot match sub category links for: " . $matchSubLinks[1]);
                    continue;
                }    
                    
                foreach ($matchSubLinkUrl[1] as $subLinkUrl){
                    if (!strlen($crawlSubCategory) || preg_match($crawlSubCategory, $subLinkUrl)){                    
                        $this->crawlArticleCategory($subLinkUrl, $storeId);                            
                    }
                }  
                
            }            
        }        
                
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($this->cArticle);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
    
    private function crawlArticleCategory($url, $storeId){
        $sTimes = new Marktjagd_Service_Text_Times();
        $position = 0;
        $countMatch = 1;
        
        // Seiten durchlaufen 
        while ($position < $countMatch){
            $this->_logger->info('crawl: ' . $url . '?direction=forward&offset=50&position=' . $position);
            
            try {
                $this->sPage->open($url . '?direction=forward&offset=50&position=' . $position);        
            } catch (Exception $ex){
                $this->_logger->err('67394: error while open ' . $url . '?direction=forward&offset=50&position=' . $position);
                return;
            }
            
            $page = $this->sPage->getPage()->getResponseBody();

            if (preg_match('#<div[^<]*class="site-content-headline"[^<]*>(.+?)</div>#', $page, $headerMatch)){
                $this->_logger->info('store address: ' . preg_replace('#<[^>]*>#', ' ', $headerMatch[1]));
            }            
            
            // Anzahl Artikel
            if (preg_match('#<span[^>]*class="navimodultxt2">([^<]+)</span>#', $page, $countMatch)){
                $countMatch = trim($countMatch[1]);
            }

            // Gültigkeit aus Überschrift ermitteln
            if (preg_match('#<h1[^>]*>[^>]*vom(.+?)bis(.+?)</h1>#', $page, $validMatch)){
               $validStart = preg_match('#\.$#', trim($validMatch[1])) ? trim($validMatch[1]) . $sTimes->getWeeksYear() : trim($validMatch[1]);
               $validEnd = preg_match('#\.$#', trim($validMatch[2])) ? trim($validMatch[2]) . $sTimes->getWeeksYear() : trim($validMatch[2]);
               
               $validStart = date("d.m.Y", strtotime($validStart));
               $validEnd = date("d.m.Y", strtotime($validEnd));
            } else {
                $this->_logger->info("company: " . $companyId . " cannot get valid dates from: " . $url);
                return;
            }                     

            // Links der Detailseiten ermitteln
            if (!preg_match_all('#<div[^>]*id="productpicture"[^>]*>\s*<a[^>]*href="([^"]+)"[^>]*>\s*<img[^>]*src="([^"]+)"[^>]*>.+?</div>#', $page, $detailLinksMatch)){#
                $this->_logger->info("company: " . $companyId . " no articles in category: " . $url . ' for store-id: ' . $storeId);
                return;
            }
            
            // Preise der Übersichtsseite ermitteln
            if (!preg_match_all('#<td[^>]*>\s*<div[^>]*class="Verkaufspreis"[^>]*>([^<]+)</div>\s*</td>#', $page, $overviewPriceMatch)){#
                $this->_logger->info("company: " . $companyId . " no articles in category: " . $url . ' for store-id: ' . $storeId);
                return;
            }
            
            // Titel des Übersichtsseite ermitteln            
            if (!preg_match_all('#<td[^>]*>\s*<div[^>]*id="posItemDescription"[^>]*>(.+?)</div>\s*</td>#', $page, $overviewTitleMatch)){
                $this->_logger->info("company: " . $companyId . " no articles in category: " . $url . ' for store-id: ' . $storeId);
                return;
            }
            
            foreach ($detailLinksMatch[1] as $idx => $detailLink){                
                // Artikel fingerprint erstellen
                $articleFingerprint = md5(
                            trim($detailLinksMatch[2][$idx])
                        .   trim($overviewPriceMatch[1][$idx])
                        .   trim($overviewTitleMatch[1][$idx])
                        .   trim($validStart)
                        .   trim($validEnd)
                        );
                
                // Wenn fingerprint bekannt, dann holen wir die Daten aus der eigenen Collection
                // und rufen nicht die Detailseite auf
                if (array_key_exists($articleFingerprint, $this->articleCache)){
                    $this->_logger->info("found duplicate: " . $this->articleCache[$articleFingerprint]);
                    
                    // Daten aus der Collection (mit neuer store_number) wiederverwenden
                    $cArticlesCache = $this->cArticle->getElements();
                    /* @var $articleFromCache Marktjagd_Entity_Api_Article */
                    foreach ($cArticlesCache as $articleFromCache){                        
                        if ($articleFromCache->getArticleNumber() == $this->articleCache[$articleFingerprint]){                     
                            $eArticle = new Marktjagd_Entity_Api_Article();
                            
                            $eArticle->setStart($articleFromCache->getStart())
                                    ->setEnd($articleFromCache->getEnd())
                                    ->setStoreNumber($storeId)
                                    ->setArticleNumber($articleFromCache->getArticleNumber())
                                    ->setTitle($articleFromCache->getTitle())
                                    ->setText($articleFromCache->getText())
                                    ->setAmount($articleFromCache->getAmount())
                                    ->setPrice($articleFromCache->getPrice())
                                    ->setSuggestedRetailPrice($articleFromCache->getSuggestedRetailPrice())
                                    ->setImage($articleFromCache->getImage());                            
                            
                            $this->cArticle->addElement($eArticle);

                            continue 2;
                        }
                    }
                } 

                $this->sPage->open($detailLink);
                $page = $this->sPage->getPage()->getResponseBody();

                $eArticle = new Marktjagd_Entity_Api_Article();
                $eArticle->setStart($validStart)
                        ->setEnd($validEnd);

                $eArticle->setStoreNumber($storeId);            
                //$eArticle->setUrl($detailLink);            

                if (preg_match('#\&productid=([^"]+)_[0-9]*"#', $page, $idMatch)){
                    $eArticle->setArticleNumber($idMatch[1]);
                }            

                if (preg_match('#<div[^>]*id="product_name"[^>]*>(.+?)</div>#', $page, $titleMatch)){
                    $eArticle->setTitle(preg_replace('#<[^>]*>#', ' ', trim($titleMatch[1])));
                }

                if (preg_match('#<div[^>]*id="product_txt"[^>]*>(.+?)</div>#', $page, $textMatch)){
                    $eArticle->setText(trim($textMatch[1]));
                }

                if (preg_match('#<div[^>]*class="[^"]*MengenangabeDetail[^"]*"[^>]*>(.+?)</div>#', $page, $packageMatch)){
                    $eArticle->setAmount($packageMatch[1]);
                }

                if (preg_match('#<div[^>]*class="[^"]*VerkaufspreisDetail[^"]*"[^>]*>(.+?)</div>#', $page, $priceMatch)){
                     $eArticle->setPrice(trim(preg_replace($this->priceMatch, $this->priceReplace, $priceMatch[1])));
                }

                if (preg_match('#<div[^>]*id="priceUVP"[^>]*>(.+?)</div>#', $page, $uvpMatch)){
                    $eArticle->setSuggestedRetailPrice(trim(preg_replace($this->priceMatch, $this->priceReplace, $uvpMatch[1])));
                }
                
                 if (preg_match('#<div[^>]*id="pricePacking"[^>]*>(.+?)</div>#', $page, $pricePackageMatch)){
                    $eArticle->setText($eArticle->getText() . '<br /><br />' . trim($pricePackageMatch[1]));
                }
                
                if (preg_match('#<img[^>]*id="bild_gross"[^>]*src="([^"]+)"[^>]*>#', $page, $imgMatch)){
                    $eArticle->setImage($imgMatch[1]);
                }

                if (substr($eArticle->getImage(), 0, 1) == '/'){
                    $eArticle->setImage($this->baseUrl . $eArticle->getImage());
                }            

                // fingerprint merken / der Artikelnummer zuordnen
                $this->articleCache[$articleFingerprint] = $eArticle->getArticleNumber();                
                
                $this->cArticle->addElement($eArticle);
            }
            $position += 50;
        }
    }    
}
