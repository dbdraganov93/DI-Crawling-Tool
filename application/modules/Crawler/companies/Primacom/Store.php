<?php

/**
 * Standortcrawler für primacom (ID: 71278)
 *
 * Class Crawler_Company_Primacom_Store
 */
class Crawler_Company_Primacom_Store extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     *
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $cStores = new Marktjagd_Collection_Api_Store();
        $url = 'http://www.primacom.de/service/shops';

        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $sPage = new Marktjagd_Service_Input_Page();
        $oPage = $sPage->getPage();
        $oPage->setMethod('POST');
        $sPage->setPage($oPage);

        $aParams = array(
            'service-partner[plz-ort]' => '%',
            'findVP' => 'OK'
        );

        $sPage->open($url, $aParams);

        $page = $sPage->getPage()->getResponseBody();
        $qStores = new Zend_Dom_Query($page, 'UTF-8');

        $nStores = $qStores->query("div[class*=\"info-container\"]");
        foreach ($nStores as $nStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $sStore = $nStore->c14n();

            $pattern = '#<div[^>]*class="span5[^"]*">\s*(.*?)\s*</div>\s*'
                       . '<div[^>]*class="span5[^"]*">\s*(.*?)\s*</div>\s*'
                       . '<div[^>]*class="span4[^"]*">\s*(.*?)\s*</div>'
                       . '#is';
            if (preg_match($pattern, $sStore, $matches)) {

                // Alle Kundenbüros + Partnershops überspringen
                if (!preg_match('#<span[^>]*class=".*?primacom[^"]*"[^>]*>#is', $matches[2])) {
                    continue;
                }

                $aAddress = preg_split('#<br>\s*</br>#', $matches[1]);

                $eStore->setStreet($sAddress->extractAddressPart($sAddress::$EXTRACT_STREET, $aAddress[0]))
                       ->setStreetNumber($sAddress->extractAddressPart($sAddress::$EXTRACT_STREET_NR, $aAddress[0]))
                       ->setZipcode($sAddress->extractAddressPart($sAddress::$EXTRACT_ZIP, $aAddress[1]))
                       ->setCity($sAddress->extractAddressPart($sAddress::$EXTRACT_CITY, $aAddress[1]))
                       ->setStoreHours($sTimes->generateMjOpenings($matches[2]))
                       ->setPhone($sAddress->normalizePhoneNumber($matches[3]));

                $cStores->addElement($eStore);
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}