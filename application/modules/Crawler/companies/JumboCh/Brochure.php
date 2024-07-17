<?php

/* 
 * Prospekt Crawler für Jumbo CH (ID: 72131)
 */

class Crawler_Company_JumboCh_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.jumbo.ch/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTranslation = new Marktjagd_Service_Text_Translation();
        $sTimes = new Marktjagd_Service_Text_Times();
        
        $cStores = $sApi->findStoresByCompany($companyId)->getElements();
        
        $aLanguages = array(
            'de' => array(
                'validityPattern' => '#gültig\s*vom\s*([^\s]+?)\s*bis\s*([^<]+)\s*#i',
                'searchUrl' => 'de/kataloge',
                'title' => 'DIE STÄRKSTEN AKTIONEN'),
            'fr' => array(
                'validityPattern' => '#valable\s*du\s*([^\s]+?)\s*au\s*([^<]+)\s*#i',
                'searchUrl' => 'fr/catalogues',
                'title' => 'LES ACTIONS LES PLUS FORTES'),
            'it' => array(
                'validityPattern' => '#valido\s*dal\s*([^\s]+?)\s*al\s*([^<]+)\s*#i',
                'searchUrl' => 'it/cataloghi',
                'title' => 'LE OFFERTE PIÙ FORTI')
        );
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aLanguages as $countryCode => $aCountryInfos) {
            $searchUrl = $baseUrl . $aCountryInfos['searchUrl'];
            
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#href="([^"]+?myflippingbook[^"]*)"#i';
            if (!preg_match($pattern, $page, $urlMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure url for country code: ' . $countryCode);
                continue;
            }
            
            $pattern = $aCountryInfos['validityPattern'];
            if (!preg_match($pattern, $page, $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity for country code: ' . $countryCode);
                continue;
            }

            if (!preg_match('#\.$#', $validityMatch[1])) {
                $validityMatch[1] .= '.';
            }

            if (!preg_match('#' . $sTimes->getWeeksYear() . '#', $validityMatch[1])) {
                $validityMatch[1] .= $sTimes->getWeeksYear();
            }
            
            $strStoreNumbers = '';
            foreach ($cStores as $eStore) {
                if (preg_match('#^' . $countryCode . '$#i', $sTranslation->findLanguageCodeForZipcode($eStore->getZipcode()))) {
                    if (strlen($strStoreNumbers)) {
                        $strStoreNumbers .= ',';
                    }
                    $strStoreNumbers .= $eStore->getStoreNumber();
                }
            }

            $sPage->open($urlMatch[1]);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#PageDataFile:\s*"(data\/jumbo_kw(\d+)_(\w?))\/content\.html"#';
            if (!preg_match($pattern, $page, $brochureInfoMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure infos for country code: ' . $countryCode);
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            
            $eBrochure->setTitle($aCountryInfos['title'])
                    ->setUrl('https://www.myflippingbook.ch/jumbo/' . $brochureInfoMatch[1] . '/KW' . $brochureInfoMatch[2] . '_Flipping-Book_' . $brochureInfoMatch[3] . '.pdf')
                    ->setStoreNumber($strStoreNumbers)
                    ->setStart($validityMatch[1])
                    ->setEnd($validityMatch[2])
                    ->setLanguageCode($countryCode)
                    ->setVariety('leaflet')
                    ->setBrochureNumber('KW' . $brochureInfoMatch[2] . '_' . $brochureInfoMatch[3] . '_' . $sTimes->getWeeksYear());
            
            $cBrochures->addElement($eBrochure);
        }
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);
        
        return $this->_response->generateResponseByFileName($fileName);
    }
}