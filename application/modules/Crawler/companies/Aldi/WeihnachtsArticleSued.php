<?php

/**
 * Store Crawler für Aldi Süd (ID: 29)
 */
class Crawler_Company_Aldi_WeihnachtsArticleSued extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.aldi-sued.de/de/sortiment/weihnachtssortiment/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a\shref="([^"]*sortiment[^"]*)"[^>]*2019_Weihnachtssortiment[^>]*>#';
        
        if (!preg_match_all($pattern, $page, $offerOverviewUrls)) {
            throw new Exception("No urls to overview pages found on $baseUrl");
        }

        $this->_logger->info('Found ' . count($offerOverviewUrls[1]) . ' overview-urls on ' . $baseUrl);

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $stores = Array();
        foreach ($sApi->findActiveBrochuresByCompany($companyId) as $brochureId => $singleBrochure) {
            $stores = $sApi->findStoresWithActiveBrochures($brochureId, $companyId);
            break;
        }

        $storeNumbers = implode(', ', array_map(function($x) { return $x['number']; }, $stores));

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($offerOverviewUrls[1] as $url) {
            sleep(1);
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a.*?title="Zur Produkt-Detailansicht".*?href="([^"]*?)"[^>]*?>#';
            if (!preg_match_all($pattern, $page, $articleUrls)) {
                throw new Exception("No article-urls found on overview page $url");
            }

            $this->_logger->info('Found ' . count($articleUrls[1]) . ' article-urls on ' . $url);

            foreach ($articleUrls[1] as $articleUrl) {
                $this->_logger->info('Scraping ' . $articleUrl);
                try {
                    $sPage->open($articleUrl);
                    $page = $sPage->getPage()->getResponseBody();
                } catch (Exception $e) {
                    $this->_logger->err('Exception: ' . $e->getMessage());
                    continue;
                }

                $eArticle = new Marktjagd_Entity_Api_Article();

                # Getting the title and manufacturer if available
                $pattern = '#<h1\sclass="detail-box--price-box--title">([.\n\t\w\W]*?h1>)#';
                if (!preg_match($pattern, $page, $titleMatch)) {
                    $this->_logger->err("No title was found on article page: $articleUrl");
                    continue;
                }

                $eArticle->setTitle(preg_replace('#®#', '', preg_replace('#<[^>]*>#','', $titleMatch[1])));

                $pattern = '#<sup>#';
                if (preg_match($pattern, $titleMatch[1], $sup)) {
                    $eArticle->setManufacturer($sub[0]);
                }

                $pattern = '#<img[^>]*?src="([^"]*?)"[^>]*?media-gallery--image[^>]*?>#';
                if (preg_match($pattern, $page, $image)) {
                    $eArticle->setImage($image[1]);
                } else {
                    $pattern = '#<img[^>]*?src="([^"]*?)"[^>]*?detail-box--image[^>]*?>#';
                    if (preg_match($pattern, $page, $image)) {
                        $eArticle->setImage($image[1]);
                    } else {
                        $this->_logger->err("No image was found on article page: $articleUrl");
                        continue;
                    }
                }

                $pattern = '#<div[^>]*?id="detail-tabcontent-1"[^>]*?>.*?<ul>(.*?)<\/ul>#';
                if (preg_match($pattern, $page, $description)) {
                    $eArticle->setText(preg_replace('#<\/li>#', '<br />', preg_replace('#<li>#', '', $description[1])));
                }

                $price = NULL;
                $pattern = '#<span\sclass="box--value[^"]*?">([^<]*?)<\/span>#';
                if (preg_match($pattern, $page, $priceValue)) {
                    $price = preg_replace('#,#', '', $priceValue[1]);
                    $pattern = '#<span\sclass="box--decimal[^"]*?">([^<]*?)<\/span>#';
                    if (preg_match($pattern, $page, $priceDecimal)) {
                        $price = $price . ',' . $priceDecimal[1];
                    }                
                } else {
                    $this->_logger->err("No price found on article page: $articleUrl");
                    continue;
                }

                $eArticle->setPrice($price);

                $pattern = '#<span\sclass="detail-box--price-box--price--amount[^"]*?">([^<]*?)<\/span>#';
                if (preg_match($pattern, $page, $amount)) {
                    $eArticle->setAmount(preg_replace('#je#', '', $amount[1]));
                }

                $eArticle->setStart(date('d.m.y'));
                $eArticle->setEnd(date('d.m.y', strtotime("monday next week")));
                $eArticle->setUrl($articleUrl);
                $eArticle->setStoreNumber($storeNumbers);
                $cArticles->addElement($eArticle);
            }
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
