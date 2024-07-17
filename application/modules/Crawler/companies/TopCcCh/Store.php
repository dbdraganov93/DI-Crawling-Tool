<?php
/**
 * Store Crawler für TopCC CH (ID: 72322)
 */

class Crawler_Company_TopCcCh_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.topcc.ch/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<article[^>]*m-addresses-list__item[^>]*>(.+?)<\/article#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#<span[^>]*itemprop="([^"]+?)"[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $singleStore, $addressInfoMatches)) {
                $this->_logger->err($companyId . ': unable to any address infos: ' . $singleStore);
                continue;
            }

            $aInfos = array_combine($addressInfoMatches[1], $addressInfoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#telefon(\s*<[^>]*>\s*)+([^<]+?)\s*<#i';
            if (preg_match($pattern, $singleStore, $phoneMatch)) {
                $eStore->setPhoneNormalized($phoneMatch[2]);
            }

            $pattern = '#fax(\s*<[^>]*>\s*)+([^<]+?)\s*<#i';
            if (preg_match($pattern, $singleStore, $faxMatch)) {
                $eStore->setFaxNormalized($faxMatch[2]);
            }

            $pattern = '#ffnungszeiten(.+?)<\/div>\s*<\/div>\s*<\/div#';
            if (preg_match($pattern, $singleStore, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            $pattern = '#<a[^>]*href="\/(standorte[^"]+?)"#';
            if (preg_match($pattern, $singleStore, $websiteMatch)) {
                $eStore->setWebsite($baseUrl . $websiteMatch[1]);
            }

            $eStore->setStreetAndStreetNumber($aInfos['streetAddress'])
                ->setZipcode($aInfos['postalCode'])
                ->setCity($aInfos['addressLocality']);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}