<?php

/**
 * Artikel Crawler für Kölle Zoo (ID: 29021)
 */
class Crawler_Company_KoelleZoo_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.koelle-zoo.de';

        $articleUrls = array('Hunde' => $baseUrl . '/produktwelten/hunde.html',
            'Katzen' => $baseUrl . '/produktwelten/katzen.html',
            'Aquaristik' => $baseUrl . '/produktwelten/aquaristik.html',
            'Kleintiere' => $baseUrl . '/produktwelten/kleintiere.html',
            'Wildvoegel' => $baseUrl . '/produktwelten/wildvoegel.html',
            'Vögel' => $baseUrl . '/produktwelten/voegel.html',
            'Teiche' => $baseUrl . '/produktwelten/teich.html',
            'Terraristik' => $baseUrl . '/produktwelten/terraristik.html',
        );

        $sPage = new Marktjagd_Service_Input_Page();
        $cArticle = new Marktjagd_Collection_Api_Article();

        foreach ($articleUrls as $cat => $articleUrl) {
            $sPage->open($articleUrl);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match_all('#<a[^>]*href="([^"]*)"[^>]*>\s*Weiterlesen\s*</a>#is', $page, $linkMatches)) {
                $this->_logger->warn('no article page links on: ' . $articleUrl);
                continue;
            }

            foreach ($linkMatches[1] as $linkMatch) {
                $sPage->open($baseUrl . $linkMatch);
                $page = $sPage->getPage()->getResponseBody();

                $qArticles = new Zend_Dom_Query($page, 'UTF-8');

                $nArticles = $qArticles->query("div[class*=\"JM_ct\"]");

                foreach ($nArticles as $nArticle) {
                    $sArticle = utf8_decode($nArticle->c14n());
                    if (preg_match('#<div class="JM_ct">(.+?)(<p[^>]*class="JM_price"[^>]*>([^<]+)</p>\s*)?</div>\s*</div>\s*</div>\s*</div>\s*#', $sArticle, $articleContentMatch)) {

                            $eArticle = new Marktjagd_Entity_Api_Article();
                            $eArticle->setUrl($baseUrl . $linkMatch);

                            if (preg_match('#<h2>([^<]+)</h2>#', $articleContentMatch[0], $match)) {
                                $eArticle->setTitle($cat . ' - ' . trim($match[1]));
                            }

                            if (preg_match('#<div[^>]*class="JM_headlines"[^>]*>.+?</div>.*?<img[^>]*src="([^"]+)"[^>]*>#', $articleContentMatch[0], $match)) {
                                $eArticle->setImage($baseUrl . $match[1]);
                            }

                            if (preg_match('#</img>\s*</div>(.+?)<div[^>]*>#', $articleContentMatch[0], $match)) {
                                $eArticle->setText(trim(preg_replace('#<table[^>]*>.+?</table>#', '', $match[1])));
                            } else {
                                $this->_logger->warn('no content for ' . $baseUrl . $linkMatch);
                            }

                            $pattern = '#<span[^>]*style="font-weight:[^>]*bold;[^>]*">\s*(\d+,\d+)[^<]*</span>#';
                            if (preg_match($pattern, $articleContentMatch[0], $priceMatch)) {

                                $eArticle->setPrice($priceMatch[1]);
                            }

                            if (!strlen($eArticle->getPrice())) {
                                $pattern = '#<b[^>]*>\s*(\d+,\d+)[^<]*<#';
                                $strPrice = '';
                                if (preg_match_all($pattern, $articleContentMatch[0], $priceMatches)) {
                                    if (count($priceMatches[1]) > 1) {
                                        $strPrice = 'ab ';
                                    }
                                    $eArticle->setPrice($strPrice . $priceMatches[1][0]);
                                }
                            }

                            #34705 skip acana and orijen article
                            if (preg_match('#(acana|orijen)#i', $eArticle->getTitle() . $eArticle->getText())) {
                                continue;
                            }
                            
                            $cArticle->addElement($eArticle);
                        }
                    }
                }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
