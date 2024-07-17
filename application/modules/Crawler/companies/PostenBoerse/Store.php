<?php
/**
 * Storecrawler für Postenbörse (ID: 69911)
 */
class Crawler_Company_PostenBoerse_Store extends Crawler_Generic_Company
{
    protected $_baseUrl = 'http://217.160.7.131/www.posten-boerse.site/';

    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($this->_baseUrl . 'standorte.html')) {
            throw new Exception('Store Crawler PostenBoerse (ID ' . $companyId . ')' . "\n"
                . 'unable to get store list from url: ' . $this->_baseUrl);
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#href="(filialen\/.+?)"#';
        if (!preg_match_all($pattern, $page, $aStoreLinks)) {
            throw new Exception(
                'Store Crawler PostenBoerse (ID ' . $companyId . ')' . "\n"
                . 'unable to get stores from url: '
                . $this->_baseUrl . 'standorte.html');
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aStoreLinks[1] as $sStoreLink) {
            $eStore = new Marktjagd_Entity_Api_Store();
            if (!$sPage->open($this->_baseUrl . $sStoreLink)) {
                $logger->log('Store Crawler PostenBoerse (ID ' . $companyId . ')' . "\n"
                    . 'unable to get stores from url: '
                    . $this->_baseUrl . 'standorte.html', Zend_Log::ERR);
                continue;
            }
            $page = $sPage->getPage()->getResponseBody();

            // Storeaddresse
            $mjAddress = new Marktjagd_Service_Text_Address();
            $pattern = '#<br[^>]*>\s*<br[^>]*>\s*(.+?)\s*<br[^>]*>\s*<br[^>]*>#';
            if (!preg_match($pattern, $page, $match)) {
                $logger->log('unable to get store address from url: '
                    . $this->_baseUrl . $sStoreLink, Zend_Log::ERR);
            }
            $aTmpAddress = explode('<br /> ', $match[1]);
            $eStore->setStreet($mjAddress->extractAddressPart('street', $aTmpAddress[0]));
            $eStore->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $aTmpAddress[0]));
            $eStore->setZipcode($mjAddress->extractAddressPart('zip', $aTmpAddress[1]));
            $eStore->setCity($mjAddress->extractAddressPart('city', $aTmpAddress[1]));

            // Bugfix PLZ Schüttdorf
            if ($eStore->getZipcode() == '484665') {
                $eStore->setZipcode('48465');
            }

            // Telefon
            $pattern = '#Telefon\s*(.+?)\s*<#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setPhone($mjAddress->normalizePhoneNumber($match[1]));
            }

            // E-Mail
            $pattern = '#mailto:(.+?)"#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setEmail($match[1]);
            }

            // Öffnungszeiten
            $mjTimes = new Marktjagd_Service_Text_Times();
            $pattern = '#Öffnungszeiten\:?\s*<br[^>]*>\s*(.+?)(\s*<br[^>]*>){2,}#';
            if (!preg_match($pattern, $page, $match)) {
                $logger->log(
                    'Store Crawler PostenBoerse (ID ' . $companyId . ')' . "\n"
                    . 'unable to get store opening data: '
                    . $this->_baseUrl . $sStoreLink, Zend_Log::ERR);
            }
            $eStore->setStoreHours($mjTimes->generateMjOpenings(preg_replace('#<br[^>]*>#', ',', $match[1])));

            // Store-Subtitle
            $eStore->setSubtitle('Sonderpostenmarkt');

            // Storenummer als Hash
            $eStore->setStoreNumber(substr(
                md5(
                    $eStore->getZipcode()
                    . $eStore->getCity()
                    . $eStore->getStreet()
                    . $eStore->getStreetNumber()
                ), 0, 25));

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}