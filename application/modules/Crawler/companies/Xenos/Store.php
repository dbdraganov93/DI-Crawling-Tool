<?php
/**
 * Storecrawler für Xenos (ID: 69910)
 */
class Crawler_Company_Xenos_Store extends Crawler_Generic_Company
{
    protected $_baseUrl = 'http://www.xenos.de/filialen';

    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $logger = Zend_Registry::get('logger');
        $sPage = new Marktjagd_Service_Input_Page();

        if (!$sPage->open($this->_baseUrl)) {
            throw new Exception($companyId . ': unable to get store list from url: ' . $this->_baseUrl);
        }

        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#codeCoordinates2\(".+?",".+?","/filialen([^\-].+?)",".+?"\)#';
        if (!preg_match_all($pattern, $page, $aStoreLinks)) {
            throw new Exception($companyId . ': unable to get stores from url: ' . $this->_baseUrl);
        }

        $cStores = new Marktjagd_Collection_Api_Store();

        foreach ($aStoreLinks[1] as $sStoreLink) {
            $eStore = new Marktjagd_Entity_Api_Store();
            if (!$sPage->open($this->_baseUrl . $sStoreLink)) {
                $logger->log($companyId . ': unable to get store detail page from url: ' . $this->_baseUrl
                        . $sStoreLink, Zend_Log::ERR);
                continue;
            }

            $page = $sPage->getPage()->getResponseBody();

            // Store-Adresse
            $mjAddress = new Marktjagd_Service_Text_Address();            

            if (!preg_match('#<div[^>]*class="zoekwinkel"[^>]*>\s*(.+?)\s*<br[^>]*>\s*([0-9]{5}\s+.+?)\s*<br[^>]*>\s*([0-9]*[\-\s]?[0-9]*)\s*<p[^>]*>#', $page, $aAddressData)) {
                if (!preg_match('#<div[^>]*class="zoekwinkel"[^>]*>.+?<br[^>]*>(.+?)\s*<br[^>]*>\s*([0-9]{5}\s+.+?)\s*<br[^>]*>\s*([0-9]*[\-\s]?[0-9]*)\s*<p[^>]*>#', $page, $aAddressData)) {
                    $logger->log($companyId . ': unable to get store address from url: ' . $this->_baseUrl
                        . $sStoreLink, Zend_Log::WARN);
                }
            }

            $pattern = '#<br[^>]*>\s*#';
            if (preg_match($pattern, $aAddressData[1])) {
                $aTmpAddress = explode('<br /> ', $aAddressData[1]);
                $eStore->setSubtitle($aTmpAddress[0]);
                $aAddressData[1] = $aTmpAddress[1];
            }
            $eStore->setStreet($mjAddress->extractAddressPart('street', $aAddressData[1]))
                   ->setStreetNumber($mjAddress->extractAddressPart('streetnumber', $aAddressData[1]))
                   ->setCity($mjAddress->extractAddressPart('city', $aAddressData[2]))
                   ->setZipcode($mjAddress->extractAddressPart('zipcode', $aAddressData[2]))
                   ->setPhone($mjAddress->normalizePhoneNumber($aAddressData[3]));

            // Öffnungszeiten
            $mjTimes = new Marktjagd_Service_Text_Times();
            $pattern = '#<tr[^>]*>\s*<td[^>]*width="90"[^>]*>(.+?)</td>\s*</tr>#';
            if (!preg_match_all($pattern, $page, $matches)) {
                $logger->log($companyId . ': unable to get opening hours from url: ' . $this->_baseUrl
                        . $sStoreLink, Zend_Log::INFO);
            }
            $aTmpTime = '';
            foreach ($matches[1] as $match) {
                if (strlen($aTmpTime)) {
                    $aTmpTime .= ', ';
                }
                $aTmpTime .= preg_replace('#</td>\s*<td[^>]*>#', ' ', $match);
            }

            $eStore->setStoreHours($mjTimes->generateMjOpenings($aTmpTime));

            // Öffnungszeiten-Notes
            $pattern = '#Extra\s*Informationen\:?(.+?)</p>#';
            if (preg_match($pattern, $page, $match)) {
                $eStore->setStoreHoursNotes(trim(preg_replace('#\s*<[^>]*>\s*#', ' ', $match[1])));
            }

            // Storenummer als Hash
            $eStore->setStoreNumber(
                substr(
                    md5($eStore->getZipcode() . $eStore->getCity() . $eStore->getStreet() . $eStore->getStreetNumber()),
                    0,
                    25)
            );
            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
