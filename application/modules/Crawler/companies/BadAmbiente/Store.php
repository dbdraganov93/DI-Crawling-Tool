<?php

/*
 * Store Crawler für Bad Ambiente (ID: 71800)
 */

class Crawler_Company_BadAmbiente_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.home.badambiente.de/';
        $searchUrl = $baseUrl . 'badambientelive';
        $detailUrl = $baseUrl . 'includes/webservice/ieQCRMWebservice.asmx/GetTraderDetail';
        
        $sPage = new Marktjagd_Service_Input_Page();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="\/([^"]+?standorte[^"]+?)"#';
        if (!preg_match_all($pattern, $page, $storeUrlMatches))
        {
            throw new Exception($companyId . ': unable to get any store urls.');
        }

        $aStoreIds = array();
        foreach ($storeUrlMatches[1] as $singleStoreUrl)
        {
            $sPage->open($baseUrl . $singleStoreUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#traderid:\'([0-9]+?)\'#';
            if (!preg_match($pattern, $page, $storeIdMatch))
            {
                $this->_logger->err($companyId . ': unable to get store id: ' . $baseUrl . $singleStoreUrl);
                continue;
            }
            $aStoreIds[$storeIdMatch[1]]['url'] = $baseUrl . $singleStoreUrl;
            
            $pattern = '#name="kuecheninspirationGalleryMainImageLink"[^>]*href="\.\.\/([^"]+?)"#';
            if (preg_match($pattern, $page, $storeImageMatch))
            {
                $aStoreIds[$storeIdMatch[1]]['image'] = $baseUrl . $storeImageMatch[1];
            }
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aStoreIds as $singleStoreIdKey => $singleStoreIdValue)
        {
            $url = $detailUrl;
            $content = json_encode(array('traderid' => $singleStoreIdKey));

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

            $json_response = json_decode(curl_exec($curl));
            curl_close($curl);
            $storeData = $json_response->d->trader;
            
            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreNumber($singleStoreIdKey)
                    ->setWebsite($singleStoreIdValue['url'])
                    ->setSubtitle($storeData->adress->firma1)
                    ->setZipcode($storeData->adress->plz)
                    ->setCity($storeData->adress->ort)
                    ->setStreet($sAddress->extractAddressPart('street', $storeData->adress->strasse))
                    ->setStreetNumber(preg_replace('#\s*\((.+?)\)$#', '', $sAddress->extractAddressPart('streetnumber', $storeData->adress->strasse)))
                    ->setPhone($sAddress->normalizePhoneNumber($storeData->contact->telefon))
                    ->setFax($sAddress->normalizePhoneNumber($storeData->contact->fax))
                    ->setEmail($sAddress->normalizeEmail($storeData->contact->email))
                    ->setImage($singleStoreIdValue['image'])
                    ->setStoreHours($sTimes->generateMjOpenings(preg_replace(array('#\##', '#\;#'), array(',', ' '), $storeData->openings->exhibition->times1)));
            
            $cStores->addElement($eStore);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        
        return $this->_response->generateResponseByFileName($fileName);
    }

}
