<?php
/**
 * Store Crawler für Lapeyre FR (ID: 72384)
 */

class Crawler_Company_LapeyreFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.lapeyre.fr/';
        $searchUrl = $baseUrl . 'tous-les-magasins';

        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $page = preg_replace('#\s{2,}#', ' ', curl_exec($ch));
        curl_close($ch);

        $pattern = '#addShop\(\s*\{\s*([^\}]+?)\s*\}\s*\)#';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeMatches[1] as $singleStore) {
            $pattern = '#\s*([^\s*:]+?)\s*:\s*\'\/?([^:]+?)\',#';
            if (!preg_match_all($pattern, $singleStore, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get any store infos: ' . $singleStore);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#lat:\s*([^\,]+?),\s*lng:\s*(.+)#';
            if (preg_match($pattern, $singleStore, $geoMatch)) {
                $eStore->setLatitude($geoMatch[1])
                    ->setLongitude($geoMatch[2]);
            }

            $strCity = $aInfos['ville'];
            if (preg_match('#\\\u([^\s]{4})#', $strCity, $unicodeCityMatch)) {
                $strCity = preg_replace('#\\\u([^\s]{4})#', chr(hexdec($unicodeCityMatch[1])), $strCity);
            }

            $strAddress = $aInfos['address1'];
            if (preg_match('#\\\u([^\s]{4})#', $strAddress, $unicodeAddressMatch)) {
                $strAddress = preg_replace('#\\\u([^\s]{4})#', chr(hexdec($unicodeAddressMatch[1])), $strAddress);
            }

            $strAddress = preg_replace("#\\\'#", "'", $strAddress);

            $pattern = '#(\d+)\s*([\/-]\s*\d+|\w\s*([\/-]\s*\w)?)?[,;]?\s+([\D]{2,})#';
            if (preg_match($pattern, trim($strAddress), $streetMatch)) {
                $eStore->setStreet(trim($streetMatch[4]))
                    ->setStreetNumber(trim($streetMatch[1] . ' ' . $streetMatch[2]));
            } else {
                $eStore->setStreet(trim($strAddress));
            }

            $eStore->setStoreNumber($aInfos['strLocId'])
                ->setZipcode($aInfos['cp'])
                ->setCity($strCity)
                ->setPhoneNormalized($aInfos['phone']);

            if (strlen($aInfos['seoUrl'])) {
                $eStore->setWebsite($baseUrl . $aInfos['seoUrl']);

                $ch = curl_init($eStore->getWebsite());
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $page = preg_replace('#\s{2,}#', ' ', curl_exec($ch));
                curl_close($ch);

                $pattern = '#<span[^>]*itemprop="openingHoursSpecification"[^>]*>(.+?)<\/span>#';
                if (preg_match_all($pattern, $page, $storeHoursListMatches)) {
                    $strTimes = '';
                    foreach ($storeHoursListMatches[1] as $singleDayList) {
                        $pattern = '#itemprop="dayOfWeek"[^>]*\#([A-Z][a-z])#';
                        if (preg_match($pattern, $singleDayList, $dayMatch)) {
                            $pattern = '#<meta[^>]*itemprop="opens"[^>]*content="([^"]+?)"[^>]*>\s*<meta[^>]*itemprop="closes"[^>]*content="([^"]+?)"[^>]*>#';
                            if (preg_match_all($pattern, $singleDayList, $timeMatches)) {
                                for ($i = 0; $i < count($timeMatches[0]); $i++) {
                                    if (strlen($strTimes)) {
                                        $strTimes .= ',';
                                    }

                                    $strTimes .= $dayMatch[1] . ' ' . $timeMatches[1][$i] . '-' . $timeMatches[2][$i];
                                }
                            }
                        }
                    }
                }

                $eStore->setStoreHoursNormalized($strTimes);

            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}