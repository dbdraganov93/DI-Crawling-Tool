<?php

/**
 * Store Crawler für Aldi Süd (ID: 29)
 */
class Crawler_Company_Aldi_ArticleSued extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.aldi-sued.de/de/angebote/';
        $desiredOffers = [
            ' this week',
            ' next week',
            #' +2 weeks'    
        ];

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<a[^(href)]*?href="([^"]*?)"[^>]*?>#';
        
        if (!preg_match_all($pattern, $page, $offerOverviewUrls)) {
            throw new Exception("No urls to overview pages found on $baseUrl");
        }

        foreach (array_unique($offerOverviewUrls[1]) as $offerOverviewUrl) {
            if (preg_match('#.*?angebote-ab-mo-(\d{3,4})#', $offerOverviewUrl, $offerOverviewUrl)) {
                foreach ($desiredOffers as $desiredOffer) {
                    $startDay = date('j', strtotime("monday $desiredOffer"));
                    $startMonth = date('m', strtotime("monday $desiredOffer"));
                    if (preg_match("#$startDay$startMonth#", $offerOverviewUrl[1], $tmp)) {
                        $mondayUrls[] = Array("url" => $offerOverviewUrl[0], "date" => $desiredOffer);
                    }
                }
            }
        }

        if (empty($mondayUrls)) {
            throw new Exception("No url for next Monday found on $baseUrl");
        }

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $stores = Array();
        foreach ($sApi->findActiveBrochuresByCompany($companyId) as $brochureId => $singleBrochure) {
            $stores = $sApi->findStoresWithActiveBrochures($brochureId, $companyId);
            break;
        }

        $storeNumbers = implode(', ', array_map(function($x) { return $x['number']; }, $stores));

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($mondayUrls as $url) {
            $sPage->open($url['url']);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a.*?title="Zur Produkt-Detailansicht".*?href="([^"]*?)"[^>]*?>#';
            if (!preg_match_all($pattern, $page, $articleUrls)) {
                throw new Exception("No article-urls found on overview page $url");
            }

            foreach ($articleUrls[1] as $articleUrl) {
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

                $tmp = $url['date'];
                $eArticle->setStart(date('d.m.y', strtotime("monday $tmp")));
                $eArticle->setEnd(date('d.m.y', strtotime("saturday $tmp")));
                $eArticle->setVisibleStart(date('d.m.y'));
                $eArticle->setUrl($url['url']);
                $eArticle->setStoreNumber($storeNumbers);
                $cArticles->addElement($eArticle);
            }
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
