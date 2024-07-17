<?php

/*
 * Store Crawler für Footlocker (ID: 67726)
 */

class Crawler_Company_Footlocker_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.footlocker.de';
        $searchUrl = '/de/filial-uebersicht';

        $sPage = new Marktjagd_Service_Input_Page();

        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $cStores = new Marktjagd_Collection_Api_Store();

        $this->_logger->info('open ' . $baseUrl . $searchUrl);
        $sPage->open($baseUrl . $searchUrl);
        $mainPage = $sPage->getPage()->getResponseBody();

        if (preg_match_all('#href="(https://www.footlocker.de/de/filiale-[0-9][^"]+)"#i', $mainPage, $storeMatch)) {
            foreach ($storeMatch[1] as $storeUrl) {
                $eStore = new Marktjagd_Entity_Api_Store();

                $this->_logger->info('open ' . $storeUrl);
                $sPage->open($storeUrl);
                $page = $sPage->getPage()->getResponseBody();

                $eStore->setWebsite($storeUrl);

                if (preg_match('#<img[^>]*src="([^"]+)"#', $page, $match)) {
                    $eStore->setImage($match[1]);
                }
                if (preg_match('#itemprop="addressLocality"[^>]*>(.+?)<#', $page, $match)) {
                    $eStore->setZipcodeAndCity($match[1]);
                }

                if (preg_match('#itemprop="streetAddress"[^>]*>(.+?)<#', $page, $match)) {
                    if ($eStore->getZipcode() == '68161') {
                        $streetNr = explode(',', $match[1]);
                        $eStore->setStreet($streetNr[0]);
                        $eStore->setStreetNumber($streetNr[1]);
                    } else {
                        $streetNr = '';
                        foreach (array_reverse(explode(',', $match[1])) as $item) {
                            $streetNr = str_replace(['"', "'", ';'], '', $item);
                            if(preg_match('#\d#',$item) && !preg_match('#(?:shop|unit|laden)#i' ,$item)){
                                break;
                            }
                        }
                        $eStore->setStreetAndStreetNumber($streetNr);
                    }
                }


                if (preg_match('#itemprop="telephone"[^>]*>(.+?)<#', $page, $match)) {
                    $eStore->setPhone($sAddress->normalizePhoneNumber($match[1]));
                }

                if (preg_match_all('#ffnungszeiten\s*</div(>.+?)</div>\s*</div>\s*</div>#', $page, $match)) {
                    $eStore->setStoreHours($sTimes->generateMjOpenings(implode(',', $match[1])));
                }

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
