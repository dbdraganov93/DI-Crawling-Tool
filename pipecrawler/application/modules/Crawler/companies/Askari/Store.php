<?php

/*
 * Store Crawler für Askari (ID: 71776)
 */

class Crawler_Company_Askari_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.angelsport.de/';
        $searchUrl = $baseUrl . '__WebShop__/special/fachmarkt_overview.jsf';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h3[^>]*>\s*<a[^>]*href="/askari/fachmaerkte/"[^>]*>(.+?)</ul#s';
        if (!preg_match($pattern, $page, $storeListMatch))
        {
            throw new Exception($companyId . ': unable to get store list.');
        }
        
        $pattern = '#<a[^>]*href="\/([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches))
        {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleUrl)
        {
            $storeDetailUrl = $baseUrl . $singleUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();
            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#kontaktdaten\:?(\s*<[^>]*>\s*)*(.+?)</p#i';
            if (!preg_match($pattern, $page, $storeInfoMatch))
            {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $aAddress = preg_split('#\s*<[^>]*>\s*#', $storeInfoMatch[2]);

            $pattern = '#id="info_text_fm"[^>]*>(.+?)</div#s';
            if (preg_match($pattern, $page, $storeTextMatch))
            {
                $pattern = '#<p[^>]*>\s*(.+?)\s*</p#';
                if (preg_match_all($pattern, $storeTextMatch[1], $storeTextMatches))
                {
                    $eStore->setText(preg_replace('#<[^>]*s[^>]*>#', '', implode('<br/>', $storeTextMatches[1])));
                }
            }

            $pattern = '#ffnungszeiten(.+?)kontakt#i';
            if (preg_match($pattern, $page, $storeHoursListMatch))
            {
                $pattern = '#<b[^>]*>\s*([a-zäöü]+?)\s*-\s*([a-zäöü]+?)\s*</b>\s*(</div>\s*)?<div[^>]*>\s*(.+?)</div>\s*<div[^>]*>\s*<br[^>]*>\s*</div>#i';
                if (preg_match_all($pattern, $storeHoursListMatch[1], $storeHoursMatches))
                {
                    for ($i = 0; $i < count($storeHoursMatches[1]); $i++)
                    {
                        $monthStart = (int) $sTimes->findNumberForMonth($storeHoursMatches[1][$i]);
                        $monthEnd = (int) $sTimes->findNumberForMonth($storeHoursMatches[2][$i]);
                        if ($monthEnd < $monthStart)
                        {
                            $monthEnd += 12;
                        }
                        if ($monthStart <= (int) date('n') && (int) date('n') <= $monthEnd)
                        {
                            $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatches[4][$i]));
                        }
                    }
                }
            }
            
            for ($i = 0; $i < count($aAddress); $i++)
            {
                if (preg_match('#^[0-9]{5}#', $aAddress[$i]))
                {
                    $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[$i - 2])))
                            ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[$i - 2])))
                            ->setCity($sAddress->extractAddressPart('city', $aAddress[$i]))
                            ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[$i]));
                }
            }

            $eStore->setPhone($sAddress->normalizePhoneNumber($aAddress[count($aAddress) - 2]));
            
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
