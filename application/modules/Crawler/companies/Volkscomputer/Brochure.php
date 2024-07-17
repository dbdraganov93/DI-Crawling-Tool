<?php

/*
 * Brochure Crawler für Volkscomputer (ID: 28350)
 */

class Crawler_Company_Volkscomputer_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.volkscomputer.info/';
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?volkscomputer[^"]+?' . date('m') . '[-|_]' . $sTimes->getWeeksYear() . '[^"]*?\.pdf)"#';
        if (!preg_match($pattern, $page, $brochureMatch))
        {
            throw new Exception($companyId . ': no brochure for this month.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($brochureMatch[1])
                ->setBrochureNumber($brochureMatch[2])
                ->setStart(date('01.m.Y'))
                ->setEnd(date('t.m.Y'))
                ->setVisibleStart($eBrochure->getStart())
                ->setTitle('Monatsangebote')
                ->setTags('Computer, Scanner, Laptop, Smartphone, Drucker, WLAN, Canon, HP, Tablet, HDD, Windows')
                ->setVariety('leaflet');
        
        if (!preg_match('#^(http)#', $eBrochure->getUrl()))
        {
            $eBrochure->setUrl($baseUrl . $eBrochure->getUrl());
        }

        $cBrochures->addElement($eBrochure);

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
