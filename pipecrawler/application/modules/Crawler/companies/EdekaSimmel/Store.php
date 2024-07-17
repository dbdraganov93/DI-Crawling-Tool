<?php

/*
 * Store Crawler für Edeka Simmel (ID: 67788)
 */

class Crawler_Company_EdekaSimmel_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://simmel.de/';
        $searchUrl = $baseUrl . 'maerkte';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a\s*href="(markt[^\#]+?)\#content"[^>]*data-image="product"[^>]*>#';

        if (!preg_match_all($pattern, $page, $storeUrlMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeUrlMatches[1] as $singleStoreUrl) {
            $storeDetailUrl = $baseUrl . $singleStoreUrl;

            $sPage->open($storeDetailUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#anschrift:?\s*</label>\s*<div[^>]*>\s*([^<]+?)\s*<#i';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address: ' . $storeDetailUrl);
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();

            $aAddress = preg_split('#\s*\,\s*#', $storeAddressMatch[1]);

            $pattern = '#telefon:?\s*</label>\s*<div[^>]*>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber($phoneMatch[1]));
            }

            $pattern = '#fax:?\s*</label>\s*<div[^>]*>\s*([^<]+?)\s*<#i';
            if (preg_match($pattern, $page, $faxMatch)) {
                $eStore->setFax($sAddress->normalizePhoneNumber($faxMatch[1]));
            }

            $pattern = '#mail:?\s*</label>\s*<div[^>]*>\s*<a\s*href="mailto:([^"]+?)"#i';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail($sAddress->normalizeEmail($mailMatch[1]));
            }

            $pattern = '#ffnungszeiten(.+?)</ul#s';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]));
            }

            $pattern = '#<small[^>]*>\s*(.+?)\s*</small#s';
            if (preg_match($pattern, $page, $additionalTextMatch)) {
                $aAdditional = preg_split('#\s*\/\s*#', $additionalTextMatch[1]);
                foreach ($aAdditional as $singleAdditional) {
                    if (preg_match('#Parkplätze:?\s*(.+)#', $singleAdditional, $parkingMatch)) {
                        $eStore->setParking($parkingMatch[1]);
                        continue;
                    }
                }
            }

            $pattern = '#<img[^>]*src="([^"]+?)"[^>]*alt="au[^"]*?sicht#i';
            if (preg_match($pattern, $page, $imageMatch)) {
                $eStore->setImage($baseUrl . $imageMatch[1]);
            }

            $eStore->setStreet($sAddress->extractAddressPart('street', $aAddress[count($aAddress) - 2]))
                    ->setStreetNumber(preg_replace('#([^\/]+?)\s*\/(.+)#', '$1', $sAddress->extractAddressPart('streetnumber', $aAddress[count($aAddress) - 2])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[count($aAddress) - 1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[count($aAddress) - 1]))
                    ->setService('Simmel Shopper, Platten-Service, Geschenk-Service, Beratungs-Service, Kundenbus, Wochenmenü, Rückgabe-Garantie')
                    ->setBonusCard('Kundenkarte')
                    ->setWebsite($storeDetailUrl)
                    ->setSubtitle('Edeka');

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
