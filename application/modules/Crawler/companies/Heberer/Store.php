<?php

/**
 * Storecrawler für Heberer (ID: 68936)
 */
class Crawler_Company_Heberer_Store extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId) {
        $cStores = new Marktjagd_Collection_Api_Store();
        $sPage = new Marktjagd_Service_Input_Page();
        
        $baseUrl = 'http://www.heberer.de';
        $searchUrl = $baseUrl . '/de/filialen/filialfinder?id=[ID]';

        for ($i=0; $i <= 500; $i++){
            $url = preg_replace('#\[ID\]#', $i, $searchUrl);
            $sPage->open($url);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match('#contentString\s*=\s*\'(.+?)\';#', $page, $aMatch)){
                $this->_logger->err('found no content string on ' . $url);
            }

            $addressText = $aMatch[1];
            $addressText = preg_replace('#<p[^>]*>#', '_cut_', $addressText);
            $addressText = preg_replace('#<br[^>]*>#', '_cut_', $addressText);
            $addressText = preg_replace('#<[^>]*>#', '', $addressText);
            $addressText = preg_replace('#\'\s*\+\s*\'#', '', $addressText);
            $addressLines = preg_split('#_cut_#', $addressText);

            if (!strlen($addressLines[1])){
                continue;
            }

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle('Wiener Feinbäckerei Herberer');

            if (!preg_match('#[0-9]#', $addressLines[1])){
                $eStore->setSubtitle($addressLines[1]);
            }

            $eStore->setStreetAndStreetNumber($addressLines[1]);
            $eStore->setZipcodeAndCity($addressLines[2]);

            if (preg_match('#>(Tel[^<]+)<#', $page, $pMatch)){
                $eStore->setPhoneNormalized($pMatch[1]);
            }

            if (preg_match('#<p[^>]*>.+?zeiten[^<]+<br[^>]*>(.+?)</p>#', $page, $hMatch)){
                $eStore->setStoreHoursNormalized($hMatch[1]);
            }

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName =$sCsv->generateCsvByCollection($cStores);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
