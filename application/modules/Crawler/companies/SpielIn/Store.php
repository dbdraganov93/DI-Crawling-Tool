<?php

/**
 * Store Crawler für Spiel-In (ID: 71264)
 */
class Crawler_Company_SpielIn_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.spiel-in.de/';
        $searchUrl = $baseUrl . 'casino/filialen.html';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<div[^>]*class="place"[^>]*id="place(.+?)"[^>]*>(.+?)<div[^>]*class="details"#s';
        if (!preg_match_all($pattern, $page, $storeMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        for ($i = 0; $i < count($storeMatches[1]); $i++) {

            $eStore = new Marktjagd_Entity_Api_Store();

            $pattern = '#Anschrift\s*<br[^>]*>\s*(.+?)\s*<br[^>]*>\s*<a#is';
            if (!preg_match($pattern, $storeMatches[2][$i], $addressMatch)) {
                $this->_logger->err($companyId . ': unable to get store address.');
                continue;
            }

            $aAddress = preg_split('#\s*<br[^>]*>\s*#', $addressMatch[1]);

            $pattern = '#Öffnungszeiten(.+?)</tbody#s';
            if (preg_match($pattern, $storeMatches[2][$i], $storeHourMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(strip_tags($storeHourMatch[1]), 'text', true));
            }

            $pattern = '#img[^>]*src="/tl_files/Themes/SPIEL-IN/images/picto/[^>]*class="([^"]+?)"#';
            if (!preg_match_all($pattern, $storeMatches[2][$i], $detailsMatches)) {
                $this->_logger->info($companyId . ': no additional store infos found.');
            }

            $strServices = '';
            foreach ($detailsMatches[1] as $singleDetail) {
                switch ($singleDetail) {
                    case 'parken': {
                            $eStore->setParking('vorhanden');
                            break;
                        }
                    case 'internet': {
                            if (strlen($strServices)) {
                                $strServices .= ', ';
                            }
                            $strServices .= 'Internetzugang vorhanden';
                            break;
                        }
                    case 'klima': {
                            if (strlen($strServices)) {
                                $strServices .= ', ';
                            }
                            $strServices .= 'Klimaanlage vorhanden';
                            break;
                        }
                    case 'massagesessel': {
                            if (strlen($strServices)) {
                                $strServices .= ', ';
                            }
                            $strServices .= 'Massagesessel vorhanden';
                            break;
                        }
                    case 'rauchen': {
                            if (strlen($strServices)) {
                                $strServices .= ', ';
                            }
                            $strServices .= 'Raucherbereich vorhanden';
                            break;
                        }
                }
            }

            $strImages = '';
            $count = 0;
            $pattern = '#<a[^>]*href="([^"]+?)"[^>]*rel="lightbox\[' . $storeMatches[1][$i] . '\]"#';
            if (preg_match_all($pattern, $page, $imageMatches)) {
                foreach ($imageMatches[1] as $singleImage) {
                    if ($count == 5) {
                        break;
                    }
                    if (strlen($strImages)) {
                        $strImages .= ',';
                    }
                    $strImages .= $baseUrl . $singleImage;
                    $count++;
                }
            }

            $eStore->setStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0]))
                    ->setStreet($sAddress->extractAddressPart('street', $aAddress[0]))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setStoreNumber($storeMatches[1][$i])
                    ->setService($strServices)
                    ->setImage($strImages);

            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
