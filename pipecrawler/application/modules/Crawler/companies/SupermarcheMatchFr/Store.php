<?php
/**
 * Store Crawler für Supermarché Match FR (ID: 72380)
 */

class Crawler_Company_SupermarcheMatchFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.supermarchesmatch.fr/';
        $searchUrl = $baseUrl . 'search-magasin.php?all=1';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="mgsbloc[^>]*>(\s*<h3.+?)<\/div>\s*<\/div#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#<p[^>]*>\s*([^<]+?)\s*<[^>]*>\s*(\d{5})\s*,\s*([^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStore);
                continue;
            }


            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*Choisir\s*ce\s*magasin#';
            if (preg_match($pattern, $singleStore, $websiteMatch)) {
                $eStore->setWebsite(preg_replace('#\/info-magasin\.php#', '', $websiteMatch[1]));
            }

            $pattern = '#\/\s*(\d+)-#';
            if (preg_match($pattern, $eStore->getWebsite(), $storeNumberMatch)) {
                $eStore->setStoreNumber($storeNumberMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($addressMatch[1], 'fr')
                ->setZipcode($addressMatch[2])
                ->setCity($addressMatch[3]);

            if (strlen($eStore->getWebsite())) {
                $storeInfoUrl = $eStore->getWebsite() . '/info-magasin.php';

                $sPage->open($storeInfoUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<script[^>]*type="application\/ld\+json"[^>]*>\s*(.+?)\s*</script#';
                if (!preg_match($pattern, $page, $infoListMatch)) {
                    $this->_logger->info($companyId . ': unable to get json info list: ' . $eStore->getWebsite());
                }

                $jInfos = json_decode($infoListMatch[1]);

                $strTimes = '';
                foreach ($jInfos->openingHoursSpecification as $singleSpecification) {
                    if (is_array($singleSpecification->dayOfWeek)) {
                        foreach ($singleSpecification->dayOfWeek as $singleDay) {
                            if (strlen($strTimes)) {
                                $strTimes .= ',';
                            }
                            $strTimes .= $singleDay . ' ' . $singleSpecification->opens . '-' . $singleSpecification->closes;
                        }
                    } else {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $singleSpecification->dayOfWeek . ' ' . $singleSpecification->opens . '-' . $singleSpecification->closes;
                    }
                }

                $pattern = '#<div[^>]*id="ficheMagasin-services"[^>]*>(.+?)</ul#';
                if (preg_match($pattern, $page, $serviceListMatch)) {
                    $pattern = '#<h3[^>]*>\s*([^<]+?)\s*<#';
                    if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                        $eStore->setService(implode(', ', $serviceMatches[1]));
                    }
                }

                $eStore->setLatitude($jInfos->geo->latitude)
                    ->setLongitude($jInfos->geo->longitude)
                    ->setPhoneNormalized($jInfos->telephone)
                    ->setStoreHoursNormalized($strTimes, 'text', TRUE, 'fr');

            }

            $cStores->addElement($eStore, TRUE);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}