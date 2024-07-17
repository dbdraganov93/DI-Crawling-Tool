<?php

/*
 * Store Crawler für V-Markt (ID: 1)
 */

class Crawler_Company_VMarkt_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.v-markt.de/';
        $searchUrl = $baseUrl . 'Maerkte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $aMarkets = array(
            'V-Markt',
            'V-Baumarkt'
        );

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aMarkets as $singleMarket) {
            $sPage->open($searchUrl . $singleMarket);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = "#href=\\\'\/([^\']+?)\\\'[^>]*>\s*zu\s*den\s*details#i";
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                throw new Exception($companyId . ': unable to get any store urls.');
            }

            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $storeDetailUrl = $baseUrl . $singleStoreUrl;

                $sPage->open($storeDetailUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
                if (!preg_match($pattern, $page, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                    continue;
                }

                $pattern = '#\/(\d+)#';
                if (!preg_match($pattern, $storeDetailUrl, $storeNumberMatch)) {
                    $this->_logger->err($companyId . ': unable to get store number: ' . $storeDetailUrl);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#href="tel:([^"]+?)"#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $pattern = '#<div[^>]*class="col-md-6"[^>]*>\s*<table[^>]*>(.+?)<\/table#';
                if (preg_match($pattern, $page, $storeHoursMatch)) {
                    $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
                }

                $pattern = '#<ul[^>]*class="vlinks[^>]*>(.+?)<\/div>\s*<\/div>\s*<\/div>#';
                if (preg_match($pattern, $page, $sectionListMatch)) {
                    $pattern = '#<\/i>\s*([^<]+?)\s*<#';
                    if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                        $strService = '';
                        $strSection = '';
                        foreach ($sectionMatches[1] as $singleSection) {
                            if (preg_match('#service#i', $singleSection)) {
                                if (strlen($strService)) {
                                    $strService .= ', ';
                                }
                                $strService .= $singleSection;
                                continue;
                            }
                            if (strlen($strSection)) {
                                $strSection .= ', ';
                            }
                            $strSection .= $singleSection;
                        }
                        $eStore->setSection($strSection)
                            ->setService($strService);
                    }
                }

                $eStore->setStoreNumber($storeNumberMatch[1])
                    ->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($storeDetailUrl);

                if (preg_match('#V-Baumarkt#', $singleMarket)) {
                    $eStore->setTitle($singleMarket);
                }

                $cStores->addElement($eStore);
            }
        }
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
