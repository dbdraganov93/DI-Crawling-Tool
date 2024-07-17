<?php

/*
 * Store Crawler für Takko Fashion (ID: 343)
 */

class Crawler_Company_Takko_Store extends Crawler_Generic_Company
{
    const FON = 'fon';
    const FAX = 'fax';
    const MAIL = 'mail';

    public function crawl($companyId)
    {
        $searchUrl = 'https://www.takko.com/de-de/stores/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sDbGeoRegion = new Marktjagd_Database_Service_GeoRegion();

        //code for Campaign
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cStoresApi = $sApi->findStoresByCompany($companyId)->getElements();

        $aDists = [];
        foreach ($cStoresApi as $eStore) {
            $aDists[$eStore->getZipcode()] = $eStore->getDistribution();
        }

        $localPath = $sFtp->connect($companyId, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Teilnahmen[\s|_|-]+([^\.]+?)\.xlsx?$#', $singleFile, $distributionMatch)) {
                $localCampaignFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aData = $sPss->readFile($localCampaignFile, TRUE)->getElement(0)->getData();
        $aCampaign = [];
        foreach ($aData as $singleColumn) {
            $aCampaign[] = $singleColumn['Filiale'];
        }

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($sDbGeoRegion->findZipCodesByNetSize(25, TRUE) as $geodata) {

            $aStores = $sPage->getDomElsFromUrlByClass($searchUrl . $this->getParamsAsString($geodata), 'stores', 'ul');
            if (!count($aStores)) {
                $this->_logger->info($companyId . ': unable to get any stores from list: ' . implode(', ', $geodata));
                continue;
            }

            foreach ($aStores[0]->getElementsByTagName('li') as $store) {
                $zipCityCountry = $sPage->getDomElsFromDomEl($store, 'address-extra')[0]->textContent;
                if (!preg_match('#Deutschland|Germany#i', $zipCityCountry)) {
                    $this->_logger->info($companyId . ': not a german store. skipping...');
                    continue;
                }

                $contacts = $this->getContacts($sPage->getDomElsFromDomEl($store, 'contact')[0]->getElementsByTagName('td'));

                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber(trim(explode(':', $sPage->getDomElsFromDomEl($store, 'storeid')[0]->textContent)[1]))
                    ->setTitle(trim(explode('-', $sPage->getDomElsFromDomEl($store, 'label')[0]->textContent)[1]))
                    ->setStreetAndStreetNumber($sPage->getDomElsFromDomEl($store, 'address1')[0]->textContent)
                    ->setZipcodeAndCity(explode(',', $sPage->getDomElsFromDomEl($store, 'address-extra')[0]->textContent)[0])
                    ->setStoreHoursNormalized($sPage->getDomElsFromDomEl($store, 'store-hours')[0]->textContent)
                    ->setPhoneNormalized($contacts[self::FON])
                    ->setEmail($contacts[self::MAIL])
                    ->setFaxNormalized($contacts[self::FAX]);

                if (in_array($eStore->getStoreNumber(), $aCampaign)) {
                    $eStore->setDistribution('Kampagne P06');
                }

                $cStores->addElement($eStore);
            }
        }

        return $this->getResponse($cStores, $companyId);
    }

    /**
     * @param array $geodata
     * @return string
     */
    private function getParamsAsString(array $geodata)
    {
        $aParams = [
            'dwfrm_storelocator_country' => 'DE',
            'dwfrm_storelocator_refineby' => 'select',
            'dwfrm_storelocator_address' => $geodata['zip'],
            'dwfrm_storelocator_latitude' => $geodata['lat'],
            'dwfrm_storelocator_longitude' => $geodata['lng'],
        ];

        $sParams = '?';
        $separator = '&';
        foreach ($aParams as $key => $item) {
            $sParams .= "$key=$item$separator";
        }
        return trim($sParams, $separator);
    }

    /**
     * @param $contacts
     * @return array
     */
    private function getContacts($contacts)
    {
        $ret = [];
        foreach ($contacts as $key => $item) {
            if (preg_match('#fon#i', $item->textContent) && $contacts->length >= ($key + 2)) {
                $ret[self::FON] = $contacts[$key + 1]->textContent;
            }
            if (preg_match('#fax#i', $item->textContent) && $contacts->length >= ($key + 2)) {
                $ret[self::FAX] = $contacts[$key + 1]->textContent;
            }
            if (preg_match('#mail#i', $item->textContent) && $contacts->length >= ($key + 2)) {
                $ret[self::MAIL] = $contacts[$key + 1]->textContent;
            }
        }
        return $ret;
    }

}
