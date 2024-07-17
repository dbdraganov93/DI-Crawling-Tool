<?php

/**
 * Storecrawler für Theo Wormland (ID: 69949)
 */
class Crawler_Company_TheoWormland_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $logger = Zend_Registry::get('logger');
        $storeUrl = 'http://www.theowormland.de/filialen';
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($storeUrl)) {
            throw new Exception($companyId . ': unable to get store-list-page from url ' . $storeUrl);
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="box\s*col10\s*filialen\s*text.+?store">\s*'
                . '<a[^>]*href="/filialen[^>]*>(.+?)</div>\s*</div>\s*</div>\s*</div>#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            $logger->log($companyId . ': unable to get stores from url '
                    . $storeUrl, Zend_Log::ERR);
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        $mjAddress = new Marktjagd_Service_Text_Address();

        foreach ($storeMatches[1] as $sStoreMatch) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $pattern = '#(.+?)</a#';
            if (preg_match($pattern, $sStoreMatch, $match)) {
                $eStore->setSubtitle($match[1]);
            }

            $pattern = '#<img[^>]*src="(.+?)"#';
            if (preg_match($pattern, $sStoreMatch, $match)) {
                $eStore->setImage('http://www.theowormland.de/' . $match[1]);
            }

            $pattern = '#<span[^>]*class="float_left"[^>]*>\s*<p[^>]*>\s*(.+?)\s*</p>\s*</span>\s*'
                    . '<span[^>]*class="float_right"[^>]*>(.+?)</p>#';

            if (!preg_match($pattern, $sStoreMatch, $match)) {
                $logger->log($companyId . ': unable to get store-address from url '
                    . $storeUrl, Zend_Log::ERR);
                continue;
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $match[1]);
            for ($i = 0; $i < count($aAddress); $i++) {
                $aAddress[$i] = preg_replace('#<[^>]*>#', '', $aAddress[$i]);
            }
            $aCalls = preg_split('#\s*<br[^>]*>\s*#', $match[2]);

            $eStore ->setCity($mjAddress->extractAddressPart('city', $aAddress[count($aAddress)-1]))
                    ->setZipcode($mjAddress->extractAddressPart('zip', $aAddress[count($aAddress)-1]))
                    ->setStreet($mjAddress->extractAddressPart('street', $aAddress[count($aAddress)-2]))
                    ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress)-2]))
                    ->setPhone($mjAddress->normalizePhoneNumber($aCalls[0]))
                    ->setFax($mjAddress->normalizePhoneNumber($aCalls[1]));

            if (3 == count($aAddress)) {
                $eStore->setSubtitle($eStore->getSubtitle() . ' ('. $aAddress[0] . ')');
            }

            $mjTimes = new Marktjagd_Service_Text_Times();
            $pattern = '#<p>\s*<span[^>]*class="float_left"[^>]*>(.+?)</span>\s*'
                    . '<span[^>]*class="float_right"[^>]*>(.+?)</span>\s*</p>#';
            if(!preg_match($pattern, $sStoreMatch, $match)) {
                $logger->log($companyId . ': unable to get store-hours from url '
                    . $storeUrl, Zend_Log::ERR);
            }

            $aDays = preg_split('#<br[^>]*>#', $match[1]);
            $aHours = preg_split('#<br[^>]*>#', $match[2]);
            $sTimes = '';
            for ($i = 0; $i<count($aDays); $i++) {
                if (strlen($sTimes)) {
                    $sTimes .= ', ';
                }
                $sTimes .= $aDays[$i] . ' ' . $aHours[$i];
            }
            $eStore->setStoreHours($mjTimes->generateMjOpenings($sTimes));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}