<?php

/*
 * Store Crawler für Peter Hahn (ID: 70963)
 */

class Crawler_Company_PeterHahn_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.peterhahn.de/';
        $searchUrl = $baseUrl . 'filialen-ueberblick';
        $sPage = new Marktjagd_Service_Input_Page();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($sPage->getDomElsFromUrlByClass($searchUrl, 'storelist-store-container') as $storeRaw) {
            $data['img'] = $sPage->getDomElsFromDomEl($storeRaw, 'storelist-store-image')[0]->getAttribute('src');
            $addrRaw = $sPage->getDomElsFromDomEl($storeRaw, 'storelist-store-address')[0];
            $data['title'] = trim($addrRaw->childNodes->item(0)->nodeValue);
            $data['strnr'] = trim($addrRaw->childNodes->item(2)->nodeValue);
            $data['zipciy'] = trim($addrRaw->childNodes->item(4)->nodeValue);
            $data['tel'] = $sPage->getDomElsFromDomEl($storeRaw, 'storelist-store-contact')[0]->childNodes->item(1)->nodeValue;
            $data['mail'] = $sPage->getDomElsFromDomEl($storeRaw, 'storelist-store-email')[0]->nodeValue;
            $data['open'] = $this->setOpenings($sPage->getDomElsFromDomEl($storeRaw, 'storelist-store-opentime')[0]);
            $data['url'] = $sPage->getDomElsFromDomEl($storeRaw, 'button ghost')[0]->getAttribute('href');

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($data['title'])
                ->setImage($data['img'])
                ->setStreetAndStreetNumber($data['strnr'])
                ->setZipcodeAndCity($data['zipciy'])
                ->setPhoneNormalized($data['tel'])
                ->setEmail($data['mail'])
                ->setStoreHoursNormalized($data['open'])
                ->setWebsite($data['url']);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param $openRaw
     * @return string
     */
    private function setOpenings($openRaw)
    {
        $open = '';
        foreach ($openRaw->getElementsByTagName('tr') as $items) {
            foreach ($items->getElementsByTagName('td') as $item) {
                if (!preg_match('#[A-Z|\d+].+#', $item->textContent, $match)) {
                    continue;
                }
                $open .= trim(preg_replace(['#–#', '# #'], ['-', ''], $match[0]));
            }
            $open .= ',';
        }
        return trim($open, ',');
    }
}
