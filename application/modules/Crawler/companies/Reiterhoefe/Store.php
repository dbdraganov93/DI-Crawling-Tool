<?php

/**
 * Store Crawler für Reiterhöfe (ID: 81190)
 */
class Crawler_Company_Reiterhoefe_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pferdeundreiter.com/';
        $overViewPageUrl = 'stallsucheergebnis.php?ganzesland=1&karte=1&wo=brd';

        $sPage = new Marktjagd_Service_Input_Page(true);
        $sPage->open($baseUrl . $overViewPageUrl);
        $overviewPage = $sPage->getPage()->getResponseBody();

        $pattern = '#addresses([^\/]+?)/script#';
        preg_match_all($pattern, $overviewPage, $matches);

        $cStores = new Marktjagd_Collection_Api_Store();
        $count = count($matches[1]);
        $i = 0;
        foreach ($matches[1] as $match) {
            $i++;
            $this->_logger->info("scraping $i/$count");
            $patternTitle = '#stallnamen[^\']+?\'([^\']+?)\'#';
            if (!preg_match($patternTitle, $match, $title)){
                print_r('$title error');
                var_dump($match);
                continue;
            }
            if (empty(trim($title[1]))) {
                print_r('error');
                var_dump($match);
                continue;
            }

            $patternLongitude = '#longitudes[^\']+?\'([^\']+?)\'#';
            if (!preg_match($patternLongitude, $match, $longitude)){
                print_r('$longitude error');
                var_dump($match);
                continue;
            }
            if (empty(trim($longitude[1]))) {
                print_r('error');
                var_dump($match);
                continue;
            }

            $patternLatitude = '#latitudes[^\']+?\'([^\']+?)\'#';
            if (!preg_match($patternLatitude, $match, $latitude)){
                print_r('$latitude error');
                var_dump($match);
                continue;
            }
            if (empty(trim($latitude[1]))) {
                print_r('error');
                var_dump($match);
                continue;
            }

            $patternShortName = '#kurznamen[^\']+?\'([^\']+?)\'#';
            if (!preg_match($patternShortName, $match, $shortName)){
                print_r('$shortName error');
                continue;
            }
            if (empty(trim($shortName[1]))) {
                print_r('error');
                continue;
            }

            try {
                $sPage->open($baseUrl . $shortName[1]);
                $detailPage = $sPage->getPage()->getResponseBody();
            } catch (Exception $e) {
                $this->_logger->err($e->getMessage());
                $this->_logger->err($baseUrl . $shortName[1]);
                continue;
            }

            $patternStreet = '#streetAddress\'>([^<]+?)<#';
            if (!preg_match($patternStreet, $detailPage, $streetAddress)){
                print_r('$streetAddress error');
//                var_dump($detailPage);
                continue;
            }
            if (empty(trim($streetAddress[1]))) {
                print_r('error');
                continue;
            }

            $patternCity = '#addressLocality\'>([^<]+?)<#';
            if (!preg_match($patternCity, $detailPage, $city)){
                print_r('$cit yerror');
//                var_dump($detailPage);
                continue;
            }
            if (empty(trim($city[1]))) {
                print_r('error');
                continue;
            }

            $patternPostalCode = '#postalCode\'>([^<]+?)<#';
            if (!preg_match($patternPostalCode, $detailPage, $postalCode)){
                print_r('$postalCode error');
//                var_dump($detailPage);
                continue;
            }
            if (empty(trim($postalCode[1]))) {
                print_r('error');
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($title[1])
                ->setLongitude($longitude[1])
                ->setLatitude($latitude[1])
                ->setStreetAndStreetNumber($streetAddress[1])
                ->setCity($city[1])
                ->setZipcode($postalCode[1]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
