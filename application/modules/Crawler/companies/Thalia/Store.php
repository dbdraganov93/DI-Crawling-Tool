<?php

/**
 * Store Crawler für Thalia (ID: 312)
 */
class Crawler_Company_Thalia_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'https://www.thalia.de/';
        $searchUrl = $baseUrl . 'shop/home/thalia-filialen/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="https://www.thalia.de/shop/home/filialen/(showBundesland/[a-z]{2,3})[^"]+?"#';
        if (!preg_match_all($pattern, $page, $countryMatches)) {
            throw new Exception($companyId . ': unable to get federal states.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($countryMatches[1] as $singleStateLink) {
            $sPage->open($searchUrl . $singleStateLink);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match_all('#(https://www.thalia.de/shop/home/filialen/showDetails/[0-9]+/)#s', $page, $linkMatches)) {
                $this->_logger->err($companyId . ': unable to get any stores for url ' . $searchUrl . $singleStateLink);
                continue;
            }

            $linkMatches[1] = array_unique($linkMatches[1]);

            foreach ($linkMatches[1] as $singleUrl) {
                usleep(500000);
                $sPage->open($singleUrl);
                $page = $sPage->getPage()->getResponseBody();

                if (!preg_match('#<[^>]*class="oStreet"[^>]*>(.+?)(\s*<[^>]*>\s*)*<[^>]*class="oZipCode"[^>]*>([^<]+?)(\s*<[^>]*>\s*)*<[^>]*class="oCity"[^>]*>(.+?)\s*<#', $page, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $singleUrl);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                if (preg_match('#<dl>\s*<dt>\s*<h4>.+?zeiten.*?</h4>(.+?)</dl>#', $page, $match)) {
                    $eStore->setStoreHoursNormalized($match[1]);
                }

                if (preg_match('#<dl[^>]*>\s*<dt>\s*<span[^>]*>.*?sonderöffnungszeiten.*?</span>(.+?)</dl>#is', $page, $match)) {
                    if (preg_match('#uhr#is', $match[1])) {
                        $eStore->setStoreHoursNotes(trim(strip_tags($match[1])));
                    }
                }

                if (preg_match('#href="tel:([^"]+)"#', $page, $match)) {
                    $eStore->setPhoneNormalized($match[1]);
                }

                if (preg_match('#href="mailto:([^"]+)"#', $page, $match)) {
                    $eStore->setEmail($match[1]);
                }

                if (preg_match_all('#<h5[^>]*class="[^"]*small\-12[^"]*"[^>]*>\s*(.+?)\s*</h5>#', $page, $match)) {
                    for ($i = 0; $i < count($match[1]); $i++) {
                        if (preg_match('#barrierefrei#i', $match[1][$i])) {
                            $eStore->setBarrierFree(1);
                            unset($match[1][$i]);
                            break;
                        }
                    }
                    $eStore->setService(implode(', ', $match[1]));
                }

                if (preg_match('#<ul[^>]*class="storeImgSlider"[^>]*>\s*<li[^>]*>\s*<img[^>]*src="([^"]+)"#', $page, $match)) {
                    $eStore->setImage($match[1]);
                }

                $eStore->setStreetAndStreetNumber($addressMatch[1])
                        ->setZipcode($addressMatch[3])
                        ->setCity($addressMatch[5])
                        ->setWebsite($singleUrl);

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
