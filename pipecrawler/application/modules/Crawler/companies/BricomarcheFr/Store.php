<?php
/**
 * Store Crawler für Bricomarché FR (ID: 72377)
 */

class Crawler_Company_BricomarcheFr_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://magasins.bricomarche.com';

        $ch = curl_init($baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $page = curl_exec($ch);
        curl_close($ch);

        $pattern = '#<h2[^>]*CitiesLinks[^>]*>(.+?)<footer#s';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#<a[^>]*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores from list.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $ch = curl_init($singleStoreUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $page = curl_exec($ch);
            curl_close($ch);

            $pattern = '#<a[^>]*class="Button\s*ButtonPrimary"[^>]*href="([^"]+?magasin-bricolage[^"]+?)"#';
            if (!preg_match($pattern, $page, $storeDetailUrlMatch)) {
                $this->_logger->err($companyId . ': unable to get store detail url: ' . $singleStoreUrl);
                continue;
            }

            $ch = curl_init($storeDetailUrlMatch[1]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            $page = curl_exec($ch);
            curl_close($ch);

            $pattern = '#\/([^\/]+)$#';
            if (!preg_match($pattern, $storeDetailUrlMatch[1], $storeNumberMatch)) {
                $this->_logger->err($companyId . ': unable to get store number:' . $storeDetailUrlMatch[1]);
                continue;
            }

            $pattern = '#itemprop="([^"]+?)"[^>]*>\s*([^<]{3,}?)\s*<#';
            if (!preg_match_all($pattern, $page, $infoMatches)) {
                $this->_logger->err($companyId . ': unable to get store infos:' . $storeDetailUrlMatch[1]);
                continue;
            }

            $aInfos = array_combine($infoMatches[1], $infoMatches[2]);

            $pattern = '#itemprop="([^"]+?)"[^>]*content="([^"]+?)"#';
            if (!preg_match_all($pattern, $page, $additionalInfoMatches)) {
                $this->_logger->err($companyId . ': unable to get additional store infos:' . $storeDetailUrlMatch[1]);
                continue;
            }

            $aAdditionalInfos = array_combine($additionalInfoMatches[1], $additionalInfoMatches[2]);

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#<h[^>]*services[^>]*>(.+?)</ul#';
            if (preg_match($pattern, $page, $serviceListMatch)) {
                $pattern = '#<h4[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $serviceListMatch[1], $serviceMatches)) {
                    $eStore->setService(implode(', ', $serviceMatches[1]));
                }
            }

            $pattern = '#<h[^>]*rayons[^>]*>(.+?)</ul#';
            if (preg_match($pattern, $page, $sectionListMatch)) {
                $pattern = '#<h4[^>]*>\s*([^<]+?)\s*<#';
                if (preg_match_all($pattern, $sectionListMatch[1], $sectionMatches)) {
                    $eStore->setSection(implode(', ', $sectionMatches[1]));
                }
            }

            $eStore->setStoreNumber($storeNumberMatch[1])
                ->setWebsite($storeDetailUrlMatch[1])
                ->setStreetAndStreetNumber(preg_replace('#\s*-\s*#', ' ', $aInfos['streetAddress']), 'fr')
                ->setZipcode($aInfos['postalCode'])
                ->setCity($aInfos['addressLocality'])
                ->setPhoneNormalized($aInfos['telephone'])
                ->setLatitude($aAdditionalInfos['latitude'])
                ->setLongitude($aAdditionalInfos['longitude'])
                ->setStoreHoursNormalized($aAdditionalInfos['openingHours']);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}