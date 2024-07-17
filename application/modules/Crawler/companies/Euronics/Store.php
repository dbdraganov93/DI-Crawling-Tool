<?php

/**
 * Store Crawler für Euronics (ID: 86)
 */
class Crawler_Company_Euronics_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $url = 'https://www.euronics.de/widgets/NfbMerchantSearch/search?&maxResults=Alle&search_key=';
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();

        for ($i = 0; $i <= 9; $i++) {
            foreach ($sPage->getDomElsFromUrlByClass($url . $i, 'item_row') as $item) {
                $address = $this->getTitleAndAddress($item, $sPage);

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setWebsite($this->getUrl($item, $sPage))
                    ->setTitle($address['title'])
                    ->setStreetAndStreetNumber($address['streetWithNr'])
                    ->setZipcodeAndCity($address['zipWithCity'])
                    ->setPhoneNormalized($address['phone'])
                    ->setFaxNormalized($address['fax'])
                    ->setEmail($address['mail'])
                    ->setStoreHoursNormalized($this->getStoreHours($item, $sPage));

                if (!$cStores->addElement($eStore)) {
                    $this->_logger->info("store $address[title] not included");
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param $item
     * @param Marktjagd_Service_Input_Page $sPage
     * @return array
     */
    private function getTitleAndAddress($item, Marktjagd_Service_Input_Page $sPage)
    {
        $ret = [];
        $rows = [
            1 => 'streetWithNr',
            2 => 'zipWithCity',
        ];
        $contact = ['phone', 'fax'];

        $col = $sPage->getDomElsFromDomEl($item, 'grid_3')[1];
        $ret['title'] = $col->getElementsByTagName('h2')[0]->nodeValue;
        foreach ($col->getElementsByTagName('div') as $key => $row) {
            if (key_exists($key, $rows)) {
                $ret[$rows[$key]] = $row->nodeValue;
            }
        }
        $i = 0;
        foreach ($sPage->getDomElsFromDomEl($item, 'service_text') as $itemContact) {
            $cleanString = $this->getCleanString(utf8_decode($itemContact->nodeValue));
            if (preg_match('#@#', $cleanString)) {
                $ret['mail'] = $cleanString;
            } elseif (preg_match('#\d+#', $cleanString)) {
                $ret[$contact[$i++]] = $cleanString;
            }
        }
        return $ret;
    }

    /**
     * @param $string
     * @return mixed
     */
    private function getCleanString($string)
    {
        $pattern = [
            '#[^A-Z|0-9]+$#i',
            '#^[^A-Z|0-9]+#i',
            '#[^\x00-\x7F]#',
            '#\s+#',
        ];
        $replacement = [
            '',
            '',
            '',
            ' ',
        ];
        return preg_replace($pattern, $replacement, $string);
    }

    /**
     * @param $item
     * @param Marktjagd_Service_Input_Page $sPage
     * @return mixed
     */
    private function getUrl($item, Marktjagd_Service_Input_Page $sPage)
    {
        foreach ($sPage->getDomElsFromDomEl($item, 'button small', 'class', 'a') as $item) {
            $linkUrl = $item->getAttribute('href');
            if (preg_match('#euronics#', $linkUrl)) {
                return $linkUrl;
            } elseif (preg_match('#mediaathome#', $linkUrl)) {
                return "https:$linkUrl";
            }
        }
        return '';
    }

    /**
     * @param $item
     * @param Marktjagd_Service_Input_Page $sPage
     * @return string
     */
    private function getStoreHours($item, Marktjagd_Service_Input_Page $sPage)
    {
        $rawOpenings = $sPage->getDomElsFromDomEl($item, 'grid_5')[0]->nodeValue;
        $rawOpenings = utf8_decode($rawOpenings);
        $rawOpenings = preg_replace('#ffnungszeiten|\n|\r#', ' ', $rawOpenings);
        $rawOpenings = preg_replace('#Uhr#i', 'Uhr,', $rawOpenings);
        $rawOpenings = preg_replace('#:#i', '', $rawOpenings);

        return $this->getCleanString($rawOpenings);
    }
}
