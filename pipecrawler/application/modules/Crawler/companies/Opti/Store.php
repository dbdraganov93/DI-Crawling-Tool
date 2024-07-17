<?php

/**
 * Store Crawler für Opti Wohnwelt (ID:71347)
 */
class Crawler_Company_Opti_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.opti-wohnwelt.de/';
        $searchUrl = $baseUrl . 'filialen';
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#filialen\s*</h4>(.+?)</div#si';
        if (!preg_match($pattern, $page, $storeListMatch)) {
            throw new Exception($companyId . ': unable to get store list.');
        }

        $pattern = '#a\s*href="([^"]+?)"#';
        if (!preg_match_all($pattern, $storeListMatch[1], $storeLinkMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }

        $pattern = '#area\s*shape.+?href="([^"]+?megastore\.de\/[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $megaStoreLinkMatches)) {
            throw new Exception($companyId . ': unable to get any stores.');
        }
        $megaStoreLinkMatches[1] = array_unique($megaStoreLinkMatches[1]);
        
        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($storeLinkMatches[1] as $singleStoreLink) {
            $sPage->open($singleStoreLink);

            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<div[^>]*class="local-countact-sub"[^>]*>(.+?</span>)\s*</div>#s';
            if (!preg_match($pattern, $page, $storeAddressMatch)) {
                throw new Exception($companyId . ': unable to get store details: ' . $singleStoreLink);
            }

            $pattern = '#<span[^>]*>\s*(.+?)</span#s';
            if (!preg_match_all($pattern, $storeAddressMatch[1], $storeAddressMatches)) {
                throw new Exception($companyId . ': unable to get any store address infos: ' . $singleStoreLink);
            }
            
            $eStore = new Marktjagd_Entity_Api_Store();
            
            if (preg_match('#72116#', $eStore->getZipcode())) {
                $aStreet = preg_split('#\s*<span[^>]*>\s*#', $eStore->getStreet());
                $eStore->setStreet(ucwords($aStreet[1]));
            }

            $pattern = '#:\s*(.+)#';
            if (preg_match($pattern, $storeAddressMatches[1][count($storeAddressMatches[1]) - 1], $mailMatch)) {
                $eStore->setEmail(strip_tags($mailMatch[1]));
            }

            $pattern = '#<b[^>]*>\s*Öffnungszeiten(.+?)\s*</div#s';
            $strStoreHoursNotes = '';
            if (preg_match($pattern, $page, $storeHoursMatch)) {
                $eStore->setStoreHours($sTimes->generateMjOpenings(preg_replace(array('#bis#', '#von#'), array('-', ''), $storeHoursMatch[1])));
                if (preg_match('#<b[^>]*>(.+?)</b>(.+)#', $storeHoursMatch[1], $storeHoursNoteMatch)) {
                    $strStoreHoursNotes = $storeHoursNoteMatch[1] . ': ' . strip_tags($storeHoursNoteMatch[2]);
                }
            }

            $pattern = '#beratertage(.+?)</ul#is';
            if (preg_match($pattern, $page, $consultantListMatch)) {
                $pattern = '#<li[^>]*>\s*(.+?)\s*</li#';
                if (preg_match_all($pattern, $consultantListMatch[1], $consultantMatches) && !preg_match('#termine\s*noch\s*nicht\s*bekannt#i', $consultantMatches[1][0])) {
                    if (strlen($strStoreHoursNotes)) {
                        $strStoreHoursNotes .= ', ';
                    }
                    $strStoreHoursNotes .= 'Beratertage: ' . trim(strip_tags(implode(', ', $consultantMatches[1])));
                }
            }
            
            $pattern = '#verkaufsoffene\s*sonntage(.+?)</ul#is';
            if (preg_match($pattern, $page, $sundayOpeningListMatch)) {
                $pattern = '#<li[^>]*>\s*(.+?)\s*</li#';
                if (preg_match_all($pattern, $sundayOpeningListMatch[1], $sundayOpeningMatches) && !preg_match('#termine\s*noch\s*nicht\s*bekannt#i', $sundayOpeningMatches[1][0])) {
                    if (strlen($strStoreHoursNotes)) {
                        $strStoreHoursNotes .= ', ';
                    }
                    $strStoreHoursNotes .= trim(strip_tags(implode(', ', $sundayOpeningMatches[1])));
                }
            }
            
            for ($i = 0; $i < count($storeAddressMatches[1]); $i++) {
                if (preg_match('#^\s*\d{4,5}#', $storeAddressMatches[1][$i])) {
                    $eStore->setZipcodeAndCity($storeAddressMatches[1][$i])
                            ->setStreetAndStreetNumber($storeAddressMatches[1][$i - 1]);
                    continue;
                }
                
                if (preg_match('#fax#i', $storeAddressMatches[1][$i])) {
                    $eStore->setFaxNormalized($storeAddressMatches[1][$i]);
                    continue;
                }
                
                if (preg_match('#mailto:([^"]+?)"#', $storeAddressMatches[1][$i], $mailMatch)) {
                    $eStore->setEmail($mailMatch[1]);
                }
            }
            
            $eStore->setWebsite($singleStoreLink)
                   ->setStoreHoursNotes($strStoreHoursNotes);
            $cStores->addElement($eStore);
        }

        foreach ($megaStoreLinkMatches[1] as $singleMegaStoreLink) {
            $singleMegaStoreLink = preg_replace('#\/?(http:\/\/www\..+)\/\/#', '$1/', $singleMegaStoreLink);
            $eStore = new Marktjagd_Entity_Api_Store();

            $sPage->open($singleMegaStoreLink);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#adresse\s*</p>(.+?)</p#i';
            if (!preg_match($pattern, $page, $addressMatch)) {
                $this->_logger->info($companyId . ': unable to get store address: ' . $singleMegaStoreLink);
                continue;
            }
            $aAddress = preg_split('#\s*<[^>]*br[^>]*>\s*#', $addressMatch[1]);
            
            $pattern = '#Tel\.:\s*(.+?)\s*<#si';
            if (preg_match($pattern, $page, $phoneMatch)) {
                $eStore->setPhone($sAddress->normalizePhoneNumber(strip_tags($phoneMatch[1])));
            }
            
            $pattern = '#e-mail:\s*<a\s*href="mailto:\s*([^"]+?)"#i';
            if (preg_match($pattern, $page, $mailMatch)) {
                $eStore->setEmail(strip_tags($mailMatch[1]));
            }

            $pattern = '#zeiten(.+?)</div#si';
            if (!preg_match($pattern, $page, $storeHoursMatch)) {
                $this->_logger->err($companyId . ': unable to get store hours: ' . $singleMegaStoreLink);
                continue;
            }
            
            $pattern = '#verkaufsoffene\s*sonntage(.+?)</div#is';
            $strStoreHoursNotes = '';
            if (preg_match($pattern, $page, $storeHoursNotesListMatch)) {
                $pattern = '#<p[^>]*>\s*(.+?)\s*</p#';
                if (preg_match_all($pattern, $storeHoursNotesListMatch[1], $storeHoursNotesMatches)) {
                    $strStoreHoursNotes = '';
                    for ($i = 1; $i < count($storeHoursNotesMatches[1]); $i++) {
                        if (strlen($strStoreHoursNotes)) {
                            $strStoreHoursNotes .= ', ';
                        }
                        $strStoreHoursNotes .= trim(strip_tags($storeHoursNotesMatches[1][$i]));
                    }
                }
            }

            $eStore->setStreet($sAddress->normalizeStreet($sAddress->extractAddressPart('street', $aAddress[0])))
                    ->setStreetNumber($sAddress->normalizeStreetNumber($sAddress->extractAddressPart('streetnumber', $aAddress[0])))
                    ->setCity($sAddress->extractAddressPart('city', $aAddress[1]))
                    ->setZipcode($sAddress->extractAddressPart('zipcode', $aAddress[1]))
                    ->setStoreHours($sTimes->generateMjOpenings($storeHoursMatch[1]))
                    ->setStoreHoursNotes($strStoreHoursNotes);
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
