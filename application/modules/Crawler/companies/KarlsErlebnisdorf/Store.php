<?php

/*
 * Store Crawler für Karls Erlebnis-Dorf (ID: 72067)
 */

class Crawler_Company_KarlsErlebnisdorf_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.karls.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*data-karls-locationPageLink[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeDetailUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < count($storeDetailUrlMatches[1]); $i++) {
            $storeDetailUrl = $baseUrl . $storeDetailUrlMatches[1][$i];

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#icon-location-arrow"[^>]*>\s*</span>\s*([^<•]+?)\s*•\s*(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#icon-phone"[^>]*>\s*</span>\s*([^<]+?)\s*<#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#icon-clock-o"[^>]*>\s*</span>\s*([^<,]+?)(,\s+auch\s+sonntags)?\s*<#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $strWeekDays = 'Mo - Sa ';
                if (strlen(($storeHoursMatch[2]))) {
                    $strWeekDays = 'Mo - So ';
                }

                $eStore->setStoreHoursNormalized($strWeekDays . $storeHoursMatch[1]);
                if (!strlen($eStore->getStoreHours())) {

                    $eStore->setStoreHoursNotes($storeHoursMatch[1]);
                }
            }

                $pattern = '#class="standort_name_info"[^>]*>\s*([^\s]+?)\s+#';
                if (preg_match($pattern, $page, $subtitleMatch)) {
                    $eStore->setSubtitle($subtitleMatch[1]);
                }

                $eStore->setAddress($addressMatch[1], $addressMatch[2])
                        ->setWebsite($storeDetailUrl);

                $cStores->addElement($eStore);
            }

            $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
            $fileName = $sCsv->generateCsvByCollection($cStores);

            return $this->_response->generateResponseByFileName($fileName);
        }
    }
    