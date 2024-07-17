<?php

/**
 * Standortcrawler für DocMorris (ID: 67868)
 */
class Crawler_Company_DocMorris_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     *
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $finderUrl = 'http://www.docmorris-apotheke.de/de/apotheken/';
        $sOpenings = new Marktjagd_Service_Text_Times();
        $sAddress = new Marktjagd_Service_Text_Address();
        $cStore = new Marktjagd_Collection_Api_Store();

        $sPage = new Marktjagd_Service_Input_Page();
        $sPage->open($finderUrl);
        $page = $sPage->getPage()->getResponseBody();
        $pattern = '#var\s*markets\s*\=\s*\[(.*?)\]#s';
        if (preg_match($pattern, $page, $matchMarkets)) {
            $aSearch = array(
                '#\s*\/\*\s*\**\s*\**\/\s*#',
                '#\'#',
                '#('
                . 'id|latitude|longitude|title|name|openings|street|city|url|zip|address|phone|fax|'
                . 'phone_fax|email|link|branch_manager|emergency_service|seoCounty|seoCity|'
                . 'seoName|seoCitySubset'
                . '):#',
                '#<a\s*href\=\"(.*?)\">#'
            );

            $aReplace = array(
                '',
                '"',
                '"$1":',
                '<a href=\'$1\'>'
            );

            $markets = preg_replace($aSearch, $aReplace, $matchMarkets[1]);

            $aMarkets = json_decode('[' . $markets . ']', true);

            foreach ($aMarkets as $market) {
                $eStore = new Marktjagd_Entity_Api_Store();
                $eStore->setStoreNumber(substr($market['id'], 0, 32))
                       ->setLatitude($market['latitude'])
                       ->setLongitude($market['longitude'])
                       ->setTitle($market['title'])
                       ->setStoreHours($sOpenings->generateMjOpenings($market['openings']))
                       ->setStreet($sAddress->extractAddressPart('street', $market['street']))
                       ->setStreetNumber($sAddress->extractAddressPart('street', $market['streetnumber']))
                       ->setZipcode($market['zip'])
                       ->setCity($market['city'])
                       ->setPhone($sAddress->normalizePhoneNumber($market['phone']))
                       ->setEmail($market['email'])
                       ->setSubtitle($market['branch_manager'])
                       ->setWebsite($finderUrl . $market['url']);
                $cStore->addElement($eStore);
            }
        } else {
            throw new Exception(
                'Standortcrawler für DocMorris (ID: 67868)' . "\n"
                . 'Couldn\#t find markets on site');
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStore);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
