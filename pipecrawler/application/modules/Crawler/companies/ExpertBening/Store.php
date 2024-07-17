<?php

/* 
 * Store Crawler für Expert Bening (ID: 67352)
 */

class Crawler_Company_ExpertBening_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();

        $baseUrl = 'https://www.expert.de/';
        $searchUrl = $baseUrl . 'cuxhaven/Fachmarkt-waehlen';

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($sPage->getDomElsFromUrlByClass($searchUrl, 'widget widget-Accordion', 'div') as $aDomStore) {

            $domNames = reset($sPage->getDomElsFromDomElByClass($aDomStore, 'widget-StoreName--text'))->nodeValue;
            $domAddresses = reset($sPage->getDomElsFromDomElByClass($aDomStore, 'widget-StoreAddress--text'))->nodeValue;
            if (!preg_match('#([^\d]+)(.+?)(\d{5})(.+)#', $domAddresses, $addressMatch)) {
                continue;
            }
            $domMail = reset($sPage->getDomElsFromDomElByClass($aDomStore, 'widget-StoreMail--text'))->nodeValue;
            $domPhone = reset($sPage->getDomElsFromDomElByClass($aDomStore, 'widget-StoreTelephone--text'))->nodeValue;
            $domFax = reset($sPage->getDomElsFromDomElByClass($aDomStore, 'widget-StoreFax--text'))->nodeValue;
            $domMaps = $sPage->getDomElsFromDomElByClass($aDomStore, 'widget widget-GoogleMaps');
            $lat = '';
            $lon = '';
            foreach ($domMaps[0]->attributes as $attr) {
                if ('data-lat' == $attr->nodeName) {
                    $lat = $attr->nodeValue;
                }
                if ('data-lng' == $attr->nodeName) {
                    $lon = $attr->nodeValue;
                }
            }
            $domOpening = reset($sPage->getDomElsFromDomElByClass($aDomStore, 'widget-StoreOpeningTimes--bodyContainer'))->nodeValue;

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($domNames)
                ->setStreet(trim($addressMatch[1]))
                ->setStreetNumber(trim($addressMatch[2]))
                ->setZipcode(trim($addressMatch[3]))
                ->setCity(trim($addressMatch[4]))
                ->setEmail(trim($domMail))
                ->setPhoneNormalized($domPhone)
                ->setFaxNormalized($domFax)
                ->setLatitude($lat)
                ->setLongitude($lon)
                ->setStoreHoursNormalized($domOpening);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}