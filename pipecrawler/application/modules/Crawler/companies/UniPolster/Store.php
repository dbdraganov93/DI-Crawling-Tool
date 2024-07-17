<?php
/**
 * Store Crawler für Uni Polster (ID: 69747)
 */

class Crawler_Company_UniPolster_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.troesser.de/';
        $searchUrl = $baseUrl . 'filialen/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $companyName = array(
            '69746' => '#\/troe?sser#',
            '69747' => '#\/uni-polster#'
        );

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class=\'avia-image-container-inner\'[^>]*>\s*<a[^>]*href=\'([^\']+?filialen[^\']+?)\'#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            if (!preg_match($companyName[$companyId], $singleStoreUrl)) {
                continue;
            }
            $sPage->open($singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)+(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $singleStoreUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#ffnungszeiten(.+?)<\/section#i';
            if (preg_match($pattern, $page, $storeHoursListMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursListMatch[1]);

                $pattern = '#>\s*([^<]+?)\s*\.?\s*([^<]+?)\s*\.?\s*bis\s*([^<]+?)\s*\.?\s*([^<]+?)\s*\.?\s*:?(\s*<[^>]*>\s*)+([^<]+?)<#i';
                if (preg_match_all($pattern, $storeHoursListMatch[1], $timeSpanMatches)) {
                    for ($i = 0; $i < count($timeSpanMatches[0]); $i++) {
                        if ((strtotime('now') > strtotime($timeSpanMatches[1][$i] . '.'
                                    . $sTimes->findNumberForMonth($timeSpanMatches[2][$i]) . '.' . $sTimes->getWeeksYear())
                                && strtotime('now') < strtotime($timeSpanMatches[3][$i] . '.'
                                    . $sTimes->findNumberForMonth($timeSpanMatches[4][$i]) . '.' . $sTimes->getWeeksYear()))
                            || (strtotime('now') > strtotime($timeSpanMatches[1][$i] . '.'
                                    . $timeSpanMatches[2][$i] . '.' . $sTimes->getWeeksYear())
                                && strtotime('now') < strtotime($timeSpanMatches[3][$i] . '.'
                                    . $timeSpanMatches[4][$i] . '.' . $sTimes->getWeeksYear()))) {
                            $eStore->setStoreHoursNormalized($timeSpanMatches[6][$i]);
                            break;
                        }
                    }
                }
            }

            $pattern = '#>\s*fon\s*:?\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#>\s*fax\s*:?\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#<img[^>]*src=\'([^\']+?)\'[^>]*itemprop="contentURL#';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($imageMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[3])
                ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}