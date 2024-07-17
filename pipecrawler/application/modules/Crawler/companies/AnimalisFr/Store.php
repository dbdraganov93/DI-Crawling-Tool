<?php
/**
 * Store Crawler für Animalis FR (ID: 72346)
 */

class Crawler_Company_AnimalisFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://magasin.animalis.com/';
        $searchUrl = $baseUrl . 'search?query=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP;
        $sPage = new Marktjagd_Service_Input_Page();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 10);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="components-outlet-item-search-result-basic"[^>]*data-lf-url="\/([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
                $this->_logger->info($companyId . ': no stores for: ' . $singleUrl);
                continue;
            }

            foreach ($storeUrlMatches[1] as $singleStoreUrl) {
                $storeDetailUrl = $baseUrl . $singleStoreUrl;

                $sPage->open($storeDetailUrl);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#<div[^>]*class="components-outlet-item-address-basic__line"[^>]*>\s*([^<]+?)(\s*<br[^>]*>[^<]+?)?\s*<\/div>\s*'
                    . '<div[^>]*class="components-outlet-item-address-basic__line"[^>]*>\s*<span[^>]*>\s*(\d{5})\s*<\/span>\s*'
                    . '<span[^>]*>\s*([^<]+?)\s*<\/span>#s';
                if (!preg_match($pattern, $page, $addressMatch)) {
                    $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                    continue;
                }

                $eStore = new Marktjagd_Entity_Api_Store();

                $pattern = '#\/(\d+?)-#';
                if (preg_match($pattern, $storeDetailUrl, $storeNumberMatch)) {
                    $eStore->setStoreNumber($storeNumberMatch[1]);
                }

                $pattern = '#<span[^>]*class="components-outlet-item-phone-basic__phone__number"[^>]*>([^<]+?)<#';
                if (preg_match($pattern, $page, $phoneMatch)) {
                    $eStore->setPhoneNormalized($phoneMatch[1]);
                }

                $strTimes = '';
                $pattern = '#<div[^>]*class="components-outlet-item-hours-retail__line__day"[^>]*>\s*([^<]+?)\s*<\/div>\s*'
                    . '<div[^>]*class="components-outlet-item-hours-retail__line__time"[^>]*>\s*'
                    . '<div[^>]*class="components-outlet-item-hours-retail__line__time__value"[^>]*>'
                    . '\s*([^<]+?)\s*<\/div>\s*<span[^>]*>\s*<\/span>\s*'
                    . '(<div[^>]*class="components-outlet-item-hours-retail__line__time__value"[^>]*>'
                    . '\s*([^<]+?)\s*<)?#';
                if (preg_match_all($pattern, $page, $storeHoursMatches)) {
                    for ($i = 0; $i < count($storeHoursMatches[0]); $i++) {
                        if (strlen($strTimes)) {
                            $strTimes .= ',';
                        }
                        $strTimes .= $storeHoursMatches[1][$i] . ' ' . $storeHoursMatches[2][$i];
                        if (count($storeHoursMatches[3])) {
                            $strTimes .= ',' . $storeHoursMatches[1][$i] . ' ' . $storeHoursMatches[4][$i];
                        }
                    }
                }

                $pattern = '#<span[^>]*class="outlet-retail__st-products__title__span"[^>]*>\s*Produits\s*<\/span>(.+?)'
                    . '<\/div>\s*<\/div>\s*<\/div>\s*<\/div>#';
                if (preg_match($pattern, $page, $sectionListMatch)) {
                    $pattern = '#<h2[^>]*service__infos__title"[^>]*>\s*([^<]+?)\s*<#';
                    if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                        $eStore->setSection(implode(', ', $sectionMatches[1]));
                    }
                }

                $pattern = '#<span[^>]*class="outlet-retail__st-services__title__span"[^>]*>\s*Services\s*<\/span>(.+?)'
                    . '<\/div>\s*<\/div>\s*<\/div>\s*<\/div>#';
                if (preg_match($pattern, $page, $serviceListMatch)) {
                    $pattern = '#<h2[^>]*service__infos__title"[^>]*>\s*([^<]+?)\s*<#';
                    if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                        $eStore->setService(implode(', ', $serviceMatches[1]));
                    }
                }

                $eStore->setStreetAndStreetNumber($addressMatch[1], 'FR')
                    ->setZipcode($addressMatch[3])
                    ->setCity($addressMatch[4])
                    ->setStoreHoursNormalized($strTimes, 'text', TRUE, 'fra');

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}