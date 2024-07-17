<?php

/**
 * Store Crawler für Deutsche Bank (ID: 71652)
 */
class Crawler_Company_DeutscheBank_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.deutsche-bank.de';
        $searchUrl = $baseUrl . '/pfb/content/pk-filialsuche.html'
                . '?label=BRANCH'
                . '&searchTerm=' . Marktjagd_Service_Generator_Url::$_PLACEHOLDER_ZIP
                . '&atmsearch=0';

        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sGen = new Marktjagd_Service_Generator_Url();

        $aUrls = $sGen->generateUrl($searchUrl, 'zipcode', 15);

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aUrls as $singleUrl) {
            $sPage->open($singleUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#href="(/pfb/content/filialfinder-detail\.html\?id[^"]+)"#';
            if (!preg_match_all($pattern, $page, $storeIdMatches)) {
                continue;
            }

            foreach ($storeIdMatches[1] as $singleStoreId) {
                try {
                    $sPage->open($baseUrl . $singleStoreId);
                    $page = $sPage->getPage()->getResponseBody();
                    $eStore = new Marktjagd_Entity_Api_Store();

                    $eStore->setWebsite($baseUrl . $singleStoreId);

                    if (preg_match('#<h2[^>]*>(.+?)</h2>#', $page, $match)) {
                        $eStore->setSubtitle($match[1]);
                    }

                    if (preg_match('#<address>(.+?)<br[^>]*>(.+?)</address>#is', $page, $match)) {
                        $eStore->setAddress($match[1], $match[2]);
                        $eStore->setStoreNumber(md5($match[1] . $match[2]));
                    }

                    if (preg_match('#<h4[^>]*>Parkmöglichkeiten</h4>\s*<ul[^>]*>(.+?)</ul>#', $page, $match)) {
                        if (preg_match_all('#<li[^>]*>(.+?)</li>#', $match[1], $submatch)) {
                            $eStore->setParking(strip_tags(implode(', ', $submatch[1])));
                        }
                    }

                    if (preg_match('#<div[^>]*id="panel-services"[^>]*>(.+?)</div>\s*</div>\s*</div>#', $page, $match)) {
                        if (preg_match_all('#<li[^>]*>(.+?)</li>#', $match[1], $submatch)) {
                            $services = array_unique($submatch[1]);

                            $eStore->setService(strip_tags(implode(', ', $services)));
                        }
                    }

                    $pattern = '#fon:?\s*<[^>]*>([^<]+?)<#';
                    if (preg_match($pattern, $page, $phoneMatch)) {
                        $eStore->setPhoneNormalized($phoneMatch[1]);
                    }

                    $pattern = '#fax:?\s*([^<]+?)<#';
                    if (preg_match($pattern, $page, $faxMatch)) {
                        $eStore->setFaxNormalized($faxMatch[1]);
                    }

                    $pattern = '#div[^>]*id="panel-openinghours"[^>]*>(.+?<)[^>]*data-id="panel-accessibility"#';
                    if (preg_match($pattern, $page, $storeHoursListMatch)) {
                        $pattern = '#class="h4"[^>]*>([^<]+?)<.+?class="ym-morning"[^>]*>([^<]+?)</span>(\s*<span[^>]*class="ym-afternoon"[^>]*>([^<]+?))?</span>\s*</span#';
                        if (preg_match_all($pattern, $storeHoursListMatch[1], $storeHoursMatches)) {
                            $strTimes = '';
                            for ($i = 0; $i < count($storeHoursMatches[0]); $i++) {
                                if (strlen($strTimes)) {
                                    $strTimes .= ',';
                                }
                                $strTimes .= $storeHoursMatches[1][$i] . ' ' . $storeHoursMatches[2][$i];
                                if (strlen($storeHoursMatches[4][$i])) {
                                    $strTimes .= ', ' . $storeHoursMatches[1][$i] . ' ' . $storeHoursMatches[4][$i];
                                }
                            }
                            $eStore->setStoreHoursNormalized($strTimes);
                        }
                    }

                    $cStores->addElement($eStore, true);
                }
                catch (Exception $e) {
                    continue;
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
