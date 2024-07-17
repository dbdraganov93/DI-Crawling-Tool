<?php

/*
 * Store Crawler für Edeka Czaikowski (ID: 71876)
 */

class Crawler_Company_EdekaGroup_StoreEkz extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://e-k-z.de/';
        $searchUrl = $baseUrl . 'standorte/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#(<div[^>]*class="marktdaten"[^>]*>.+?)</ul>\s*</div#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<(div|p)[^>]*>\s*(.+?)\s*</(p|div)#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos: ' . $singleStore);
            }

            $aAddress = preg_split('#\s*<[^>]*>\s*#', $infoMatches[2][0]);

            $pattern = '#<li[^>]*>\s*([^<]+?)\s*<#';
            if (preg_match_all($pattern, $singleStore, $sectionMatches)) {
                $eStore->setSection(implode(', ', $sectionMatches[1]));
            }

            $eStore->setAddress($aAddress[0], $aAddress[1])
                    ->setPhoneNormalized($infoMatches[2][1])
                    ->setFaxNormalized($infoMatches[2][2])
                    ->setStoreHoursNormalized($infoMatches[2][3]);

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
