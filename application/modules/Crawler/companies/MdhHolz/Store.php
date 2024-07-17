<?php

/*
 * Store Crawler für MDH Marketingverbund (ID: 71874)
 */

class Crawler_Company_MdhHolz_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.mdh-holz.de/';
        $searchUrl = $baseUrl . 'holzhaendler-holzfachmaerkte/brd.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h2[^>]*class="csc-firstHeader"[^>]*>(.+?)</p>\s*</div>\s*</div>\s*</div>#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#>\s*([^<]+)(\s*<[^>]*>\s*)+(\d{5}\s+[A-Z][^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#tel([^<]+?)<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[1]);
            }

            $pattern = '#fax([^<]+?)<#i';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[1]);
            }

            $pattern = '#<a[^>]*href="(http[^"]+?)"#';
            if (preg_match($pattern, $singleStore, $websiteMatch)) {
                $eStore->setWebsite($websiteMatch[1]);
            }

            $pattern = '#>([^<]+?\(at\)[^<]+?)<#';
            if (preg_match($pattern, $singleStore, $mailMatch)) {
                $eStore->setEmail($sAddress->normalizeEmail($mailMatch[1]));
            }
            
            $pattern = '#Schwerpunkte:\s*<[^>]*>\s*([^<]+?)\s*<#s';
            if (preg_match($pattern, $singleStore, $sectionMatch)) {
                $eStore->setSection($sectionMatch[1]);
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[3]);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
