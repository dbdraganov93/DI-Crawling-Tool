<?php

/*
 * Store Crawler für Polster & Pohl Reisen (ID: 69823)
 */

class Crawler_Company_PolsterPohl_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.polster-pohl.de/';
        $searchUrl = $baseUrl . 'content/ueber_uns.php?p=2';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#div[^>]*class="rahmen_bilderkasten"[^>]*>(.+?)<hr#';
        if (!preg_match($pattern, $page, $storeListMatch))
        {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<b[^>]*>(.+?)<div[^>]*class="clear_both">\s*</div>\s*</div>#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeMatches))
        {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }


        $pattern = '#<h2[^>]*>Wir\s*beraten\s*Sie\s*gerne\s*([^<]+?)<#';
        if (!preg_match($pattern, $page, $storeHoursMatch))
        {
            $this->_logger->err($companyID . ': unable to get store hours.');
        }
        
        $strStoreHours = $sTimes->generateMjOpenings(preg_replace(array('#von#', '#Uhr#'), array('', ''), $storeHoursMatch[1]));
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore)
        {
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#>\s*([^<]+?)\s*</div>\s*<div[^>]*>\s*([0-9]{5}[^<]+?)\s*<#';
            if (!preg_match($pattern, $singleStore, $storeAddressMatch))
            {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }

            $pattern = '#fon[^>]*>(.+?)</div>\s*</div#';
            if (preg_match($pattern, $singleStore, $phoneMatch))
            {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }

            $pattern = '#fax[^>]*>(.+?)</div>\s*</div#i';
            if (preg_match($pattern, $singleStore, $faxMatch))
            {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }

            $pattern = '#mailto:([^"]+?)"#';
            if (preg_match($pattern, $singleStore, $mailMatch))
            {
                $eStore->setEmail($sAddress->normalizeEmail($mailMatch[1]));
            }

            $eStore->setStreet($sAddress->extractAddressPart('street', $storeAddressMatch[1]))
                    ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $storeAddressMatch[1]))
                    ->setCity($sAddress->extractAddressPart('city', $storeAddressMatch[2]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $storeAddressMatch[2]))
                    ->setStoreHours($strStoreHours);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
