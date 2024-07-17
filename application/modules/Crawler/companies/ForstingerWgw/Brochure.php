<?php

/*
 * Brochure Crawler für Forstinger AT (ID: 72290)
 */

class Crawler_Company_ForstingerWgw_Brochure extends Crawler_Generic_Company {
    
    public function crawl($companyId) {
        $baseUrl = 'https://www.forstinger.com/';
        $searchUrl = $baseUrl . 'angebote/flugblatt/';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#angebote(\s*gültig)?\s*bis\s*([^\s]+)\s*#i';
        if (!preg_match($pattern, $page, $validityEndMatch)) {
            throw new Exception ($companyId . ': unable to get brochure validity end.');
        }
        
        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*(jetzt|gleich)\s*durchblättern#is';
        if (!preg_match($pattern, $page, $brochureUrlMatch)) {
            throw new Exception ($companyId . ': unable to get brochure url.');
        }
        
        $ch = curl_init($brochureUrlMatch[1]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $pattern = '#HTTP\/1\.1\s*307\s*Temporary\s*Redirect.*Location:\s*([^\s]+?)\s#s';
        if (!preg_match($pattern, $result, $brochureRedirectedUrlMatch)) {
            throw new Exception ($companyId . ': unable to get brochure redirect url.');
        }
        
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        
        $eBrochure->setUrl($brochureRedirectedUrlMatch[1] . 'epaper/ausgabe.pdf')
                ->setTitle('Wochenangebote')
                ->setEnd($validityEndMatch[2])
                ->setVariety('leaflet');
        
        $cBrochures->addElement($eBrochure);
        
        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }
}
