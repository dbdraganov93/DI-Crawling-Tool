<?php

/**
 * Store Crawler für Conrad (ID: 53)
 */
class Crawler_Company_Conrad_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.conrad.de';
        $searchUrl = $baseUrl . '/de/filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="(/de/filialen/filiale?-[^"]+.html)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $storeUrlMatches[1] = array_unique($storeUrlMatches[1]);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStore) {
            $searchUrl = $baseUrl . $singleStore;
            $this->_logger->info('open  ' . $searchUrl);

            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#>\s*([^<]+?)(\s*<[^>]*>\s*)+(\d{5}\s+[A-ZÄÖÜ][^<]+?)\s*<#s';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            if (preg_match('#ffnungszeiten(.+?)<\/div>#', $page, $storeHoursMatch)) {
                $eStore->setStoreHoursNormalized($storeHoursMatch[1]);
            }

            if (preg_match('#mailto:([^"]+\@[^"]+?)"#', $page, $match)) {
                $eStore->setEmail($match[1]);
            }

            if (preg_match('#<b[^>]*>\s*Parken<\/b>\s*<\/td>\s*<td[^>]*>([^<]+?)<#', $page, $match)) {
                $eStore->setParking($match[1]);
            }

            if (preg_match('#<b[^>]*>Wir freuen uns auf Sie!<\/b>\s*<\/p>\s*<ul[^>]*>\s*<li[^>]*>(.+?)<\/ul>#', $page, $match)) {
                $services = $match[1];
                $services = preg_replace('#\s*<li[^>]*>\s*#', ',', $services);
                $services = preg_replace('#\s*<\/li>\s*#', '', $services);

                $eStore->setService($services);
            }

            $eStore->setAddress(preg_replace('#\s+-\s+[A-ZÄÖÜ].+#', '', $addressMatch[1]), $addressMatch[3])
                ->setWebsite($searchUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}