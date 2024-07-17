<?php

/*
 * Store Crawler für Radlbauer (ID: 71803)
 */

class Crawler_Company_Radlbauer_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.radlbauer.de/';
        $searchUrl = $baseUrl . 'index.php?cl=dd_standortfinder';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#a[^>]*href="([^"]+)"[^>]*>Filiale\s*ansehen#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach (array_unique($storeMatches[1]) as $singleStoreUrl) {
            $sPage->open($baseUrl . $singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#class="dd-detail-address[^>]*>\s*(.+?)\s*</div>\s*</div>#';
            if (!preg_match($pattern, $page, $infoListMatch)) {
                $this->_logger->err($companyId . ': unable to get store info list.');
                continue;
            }
            
            $pattern = '#>\s*([^<]+?)\s*<[^>]*>\s*(\d{5}\s+[^<]+?)\s*<#';
            if (!preg_match($pattern, $infoListMatch[1], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#href="tel:([^"]+?)"#';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }

            $pattern = '#href="mailto:([^"]+?)"#';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($sAddress->normalizeEmail($mailMatch[1]));
            }

            $pattern = '#ffnungszeiten(.+?)</p#';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }

            $eStore->setAddress($addressMatch[1], $addressMatch[2])
                    ->setWebsite($singleStoreUrl);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
