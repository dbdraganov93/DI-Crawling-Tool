<?php
/**
 * Storecrawler für Bellandris (ID: 29063)
 */
class Crawler_Company_Bellandris_Store extends Crawler_Generic_Company
{
    protected $_baseUrl = 'http://www.bellandris.de/unternehmen/standortsuche.html';

    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $subtitle = 'Hier wachsen Ideen!';

        if (!$sPage->open($this->_baseUrl)) {
            throw new Exception('couldn\'t open store list url for company ' . $companyId);
        }

        $page = $sPage->getPage()->getResponseBody();
        $sAddress = new Marktjagd_Service_Text_Address();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStore = new Marktjagd_Collection_Api_Store();

        $patternStoreList = '#var\s*market\s*=\s*(.+?)locations\.push#s';
        if (!preg_match_all($patternStoreList, $page, $storeMatches)) {
            throw new Exception('Couldn\'t find any stores for company ' . $companyId
                . ', url: ' . $this->_baseUrl);
        }

        foreach ($storeMatches[1] as $singleStore) {
            $eStore = new Marktjagd_Entity_Api_Store();
            $storeProperties = preg_split('#;#', $singleStore);
            foreach ($storeProperties as $storeProperty) {
                $patternProperty = '#\["([^"]+?)"\]\s*=\s*"([^"]+?)"#s';
                if (preg_match($patternProperty, $storeProperty, $matchProp)) {
                    switch ($matchProp[1]) {
                        case 'name1':
                            $eStore->setSubtitle($matchProp[2]);
                            break;
                        case 'street':
                            $eStore->setStreet($sAddress->extractAddressPart('street', $matchProp[2]))
                                   ->setStreetNumber($sAddress->extractAddressPart('streetnumber', $matchProp[2]));
                            break;
                        case 'zip':
                            $eStore->setZipcode($matchProp[2]);
                            break;
                        case 'city':
                            $eStore->setCity($matchProp[2]);
                            break;
                        case 'latitude':
                            $eStore->setLatitude($matchProp[2]);
                            break;
                        case 'longitude':
                            $eStore->setLongitude($matchProp[2]);
                            break;
                        case 'www':
                            $eStore->setWebsite($matchProp[2]);
                            break;
                        case 'email':
                            $eStore->setEmail($matchProp[2]);
                            break;
                        case 'phone':
                            $eStore->setPhone($sAddress->normalizePhoneNumber($matchProp[2]));
                            break;
                        case 'fax':
                            $eStore->setFax($sAddress->normalizePhoneNumber($matchProp[2]));
                            break;
                        case 'uid':
                            $eStore->setStoreNumber($matchProp[2]);
                            break;
                        case 'oeffnungszeiten':
                            $eStore->setStoreHoursNormalized($matchProp[2]);
                            break;
                        default:
                            break;
                    }
                }
            }
            
            $cStore->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}