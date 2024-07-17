<?php

/**
 * Store Crawler für Dresen (ID: 73659)
 */
class Crawler_Company_Dresen_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $url = "https://www.dresen.de/";
        $searchUrl = "$url/standorte/";

        $cStores = new Marktjagd_Collection_Api_Store();  
        foreach ($sPage->getDomElsFromUrlByClass($searchUrl, 'filialbox', 'div', true) as $storeElement) {
            $titleElement = $sPage->getDomElsFromDomEl($storeElement, 'locationtitle')[0];
            $storePage = $storeElement->baseURI . $titleElement->getElementsByTagName('a')[0]->getAttribute('href');
            $storeHours = $this->extractStoreHours($sPage, $storePage);

            $address = $sPage->getDomElsFromDomEl($storeElement, 'adresse')[0];
            $countParagraphs = $address->getElementsByTagName('p')->length;

            if ($countParagraphs === 2) {
                $streetAndNumber = $address->getElementsByTagName('p')[0]->childNodes[5]->textContent;
                $zipcodeAndCity = $address->getElementsByTagName('p')[0]->childNodes[7]->textContent;
                $phone = preg_replace('#\D#', '', $address->getElementsByTagName('p')[1]->childNodes[0]->textContent);
                $email = $this->extractEmail($address->getElementsByTagName('p')[1]->childNodes[5]->textContent);
            }
            else {       
                $streetAndNumber = $address->getElementsByTagName('p')[1]->childNodes[0]->textContent;
                $zipcodeAndCity = $address->getElementsByTagName('p')[1]->childNodes[2]->textContent;
                $phone = preg_replace('#\D#', '', $address->getElementsByTagName('p')[2]->childNodes[0]->textContent);
                $email = $this->extractEmail($address->getElementsByTagName('p')[3]->textContent);
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber(md5($titleElement->textContent))
                ->setTitle(trim($titleElement->textContent))
                ->setWebsite($storePage)
                ->setStreetAndStreetNumber($streetAndNumber)
                ->setZipcodeAndCity($zipcodeAndCity)
                ->setPhoneNormalized($phone)
                ->setEmail($email);

            if ($storeHours) {
                $eStore->setStoreHoursNormalized($storeHours);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

    private function extractEmail($haystack)
    {
        //pattern to match an email address even when "(at)" is used instead of "@"
        $pattern = '#\b([a-z\-\.]+(@|\(at\))[a-z\-\.]+\.[a-z]+)\b#';
        if (preg_match_all($pattern, $haystack, $matches) === 1) {
            if ($matches[2][0] === '(at)') {
                return str_replace('(at)', '@', $matches[1][0]);
            } else {
                return $matches[1][0];
            }
        }
        return '';
    }

    private function extractStoreHours($sPage, $storePage)
    {
        $elements = $sPage->getDomElsFromUrlByClass($storePage, 'openblock', 'div', true);
        if ($elements && sizeof($elements) == 1) {
            $childs = $elements[0]->getElementsByTagName('p');
            $secondChild = $childs[1];
            $hours = array();
            foreach ($secondChild->childNodes as $node) {
                if ($node->tagName) {
                    continue;
                }
                $hours[] = $node->textContent;
            }
            return implode(' ', $hours);
        }
        return false;
    }
}
