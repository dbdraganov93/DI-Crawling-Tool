<?php

/**
 * Store Crawler für Ihr Platz (ID: 95)
 */

class Crawler_Company_IhrPlatz_Store extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.ihrplatz.de';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();
        $cStores = new Marktjagd_Collection_Api_Store();

        $dayPattern = array(
            '#bis#i',
            '#Uhr#i',
            '#und#',
            '#\(.*?\)#',
            '#([a-z])\.#i',
            '#(\d)\.|\,(\d)#',
            '#,#',
            '#\s{2,}#'
        );
        $dayReplacement = array(
            '-',
            '',
            '',
            '',
            '$1',
            '$1:$2',
            '',
            ' '
        );


        $pattern = '#<a [^>]*?href="([^"]*?)"[^>]*?>Märkte</a>#i';

        if (!preg_match($pattern, $page, $match)) {
            throw new Exception($companyId . ': link to store overview page \'Märkte\' not found in ' . $domain);
        }

        $storeListPage = $match[1];

        $storeListPage = $baseUrl .'/' . $storeListPage;
        $sPage->open($storeListPage);

        $listPage = $sPage->getPage()->getResponseBody();

        #find zipcode blocks
        $pattern = '#<div [^>]*?class="accordion-inner"[^>]*>(.*?</div>\s*?</div>\s*?</div>)#';
        if (!preg_match_all($pattern, $listPage, $matches)) {
            throw new Exception('can\'t find any zipcode block: ' . $storeListPage);
        }

        foreach ($matches[1] as $blockNum => $data) {
            $pattern = '#<div[^>]*?>\s*?<div[^>]+?>(.*?)</div>\s*?</div>#i';
            if (!preg_match_all($pattern, $data, $addressMatches)) {
                $this->_logger->err($companyId . ': can\'t find data in store block: ' . $data);
                continue;
            }

            foreach ($addressMatches[1] as $addressNum => $addressData) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $pieces = preg_split('#<br[^>]*?>#',$addressData);

                $eStore->setSubtitle(trim(preg_replace('#\"*Ihr Platz\"*#i','',$pieces[0])));
                $eStore->setStreetAndStreetNumber($pieces[1]);

                $pattern = '#<b>(\d{5})\s*?([^<]*?)</b>$#';
                if (!preg_match($pattern, $pieces[2], $match)) {
                    $this->_logger->err($companyId . ': zipcode and city not matched in ' . $pieces[2]);
                    continue;
                } else {
                    $eStore->setZipcode(trim($match[1]));
                    $eStore->setCity(trim($match[2]));
                }

                $hours = array();

                for ($i=4;$i<count($pieces);$i++) {

                    $string = $pieces[$i];

                    // phone
                    $pattern = '#Tel[^0-9]*(.*?)$#';
                    if (preg_match($pattern, $string, $match)) {
                        $eStore->setPhoneNormalized(strip_tags($match[1]));
                        continue;
                    }

                    // hours
                    $pattern = '#(\d{2})\.|:(\d{2}).*?(\d{2})\.|:(\d{2})#';
                    if (preg_match($pattern,$string,$match)) {
                        // date included? -> notes
                        if (preg_match('#\d{2}\.\d{2}\.\d{2,4}#',$string)) {
                            $eStore->setStoreHoursNotes(strip_tags($string));
                        } else {
                            $temp = strip_tags($string);
                            $temp = preg_replace($dayPattern, $dayReplacement, $temp);
                            $hours[] = trim($temp);
                            if (preg_match('#So#',$temp)) {
                                $eStore->setStoreHoursNotes($eStore->getStoreHoursNotes() . '<br>verkaufsoffener Sonntag!');
                            }
                        }
                    }

                } // end pieces for hours an telephone

                if (count($hours)>0) {
                    $eStore->setStoreHoursNormalized(implode(', ' , $hours));
                }

                preg_match('#(\d{1,})#',$eStore->getStreetNumber(), $matchStreetNum);
                $eStore->setStoreNumber($eStore->getZipcode() . $matchStreetNum[1]);

                $cStores->addElement($eStore);
            } // end Address Data loop

        } // end zipcode blocks
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}