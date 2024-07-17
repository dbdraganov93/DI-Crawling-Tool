<?php

/* 
 * Prospekt Crawler für Thomas Philipps (ID: 352)
 */

class Crawler_Company_ThomasPhilipps_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $baseUrl = 'https://www.thomas-philipps.de/';
        $searchUrl = $baseUrl . 'Prospekt';
        $sPage = new Marktjagd_Service_Input_Page();
        
        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();
        
        $pattern = '#<div[^>]*class="brochure-box"[^>]*>(.+?)</table#s';
        if (!preg_match($pattern, $page, $brochureInfoMatch)) {
            throw new Exception ($companyId . ': unable to get brochure info box.');
        }
        
        $pattern = '#gültig\s*vom\s*([^\s]+?)\s*bis\s*([^<]+?)\s*<#';
        if (!preg_match($pattern, $brochureInfoMatch[1], $validityMatch)) {
            $this->_logger->err ($companyId . ': unable to get brochure validity.');
        }
        
        $pattern = '#<a[^>]*href="\/([^"]+?)"[^>]*>\s*als\s*PDF\s*herunterladen#';
        if (!preg_match($pattern, $brochureInfoMatch[1], $pathMatch)) {
            $this->_logger->err ($companyId . ': unable to get brochure data.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Thomas Philipps: Wochenangebote')
            ->setStart($validityMatch[1])
            ->setEnd($validityMatch[2])
            ->setVariety('leaflet')
            ->setUrl($baseUrl . $pathMatch[1]);
        
        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures);
    }
}
