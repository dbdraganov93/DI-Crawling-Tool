<?php

/**
 * Store crawler for Paramount (ID: )
 */

class Crawler_Company_Paramount_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.kino.de/';
        $searchUrl = $baseUrl . 'kinoprogramm/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#(https://www.kino.de/kinoprogramm/stadt/[a-z]/)#';
        if (!preg_match_all($pattern, $page, $cityListMatches)) {
            throw new Exception($companyId . ': unable to get any city lists.');
        }

        $cityUrls = [];
        $aAddresses = [];
        foreach ($cityListMatches[1] as $cityListUrl) {
            $sPage->open($cityListUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#Kinoprogramm\s*nach\s*Stadt<\/h1>\s*<ol[^>]*class="list-inline">(.+?)<\/ol>#';
            if (!preg_match($pattern, $page, $cityListMatches)) {
                $this->_logger->err($companyId . ': unable to get city list from ' . $cityListUrl);
                continue;
            }

            $pattern = '#<a[^>]*href="([^"]+?)"#';
            if (!preg_match_all($pattern, $cityListMatches[1], $cityMatches)) {
                $this->_logger->err($companyId . ': unable to get any cities from list.');
                continue;
            }
            $cityUrls = array_merge($cityUrls, $cityMatches[1]);
        }
        foreach ($cityUrls as $singleCityUrl) {
            $sPage->open($singleCityUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<h2[^>]*>\s*([^<]+?)\s*<\/h2>\s*<address[^>]*style="display:[^"]*inline"[^>]*>\s*([^,]+?)\s*,\s*([^<]+?)\s*<\/address>#';
            if (!preg_match_all($pattern, $page, $addressMatches)) {
                $this->_logger->info($companyId . ': unable to get any addresses from ' . $singleCityUrl);
                continue;
            }
            for ($i = 0; $i < count($addressMatches[0]); $i++) {
                $aAddresses[$addressMatches[2][$i] . ', ' .  $addressMatches[3][$i]] = $addressMatches[1][$i];
            }
        }

        $fileName = APPLICATION_PATH . '/../public/files/paramount.csv';
        $fh = fopen($fileName, 'w');
        foreach ($aAddresses as $name => $singleAddress) {
            fputcsv($fh, [$name, $singleAddress], ';');
        }
        fclose($fh);
        Zend_Debug::dump($fileName);
        die;
    }
}