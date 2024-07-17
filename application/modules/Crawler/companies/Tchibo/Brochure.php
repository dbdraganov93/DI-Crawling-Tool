<?php

/*
 * Brochure Crawler für Tchibo (ID: 25)
 */

use function Couchbase\defaultDecoder;

class Crawler_Company_Tchibo_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sTimes = new Marktjagd_Service_Text_Times();

        $baseUrl = 'http://www.tchibo.de/';
        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match('#<a[^>]*href="\.\/([^"]+?)"[^>]*>\s*Katalog\s*<\/a#', $page, $brochureInfoPageMatch)) {
            throw new Exception($companyId . ': unable to get brochure info page.');
        }

        $sPage->open($baseUrl . $brochureInfoPageMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        if (!preg_match_all('#src="(http:\/\/magazin.tchibo.de[^"]+KatalogKW\d+_(\w+)\_\w+\/blaetterkatalog\/blaetterkatalog\/)#si', $page, $brochureInfoListMatch)) {
            throw new Exception($companyId . ': unable to get brochure info list.');
        }

        $dateFormat = 'Y-m';
        $possible_month_matches = $brochureInfoListMatch[2];
        while ($month_match = current($possible_month_matches)) {
            $month = $sTimes->findNumberForMonth($month_match);
            if ($month == false) {
                next($possible_month_matches);
                continue;
            } elseif ($month == date('m', strtotime('now + 1 month'))) {
                $brochureNumber = date($dateFormat, strtotime('next month'));
                $key = key($possible_month_matches);
                break;
            } elseif ($month == date('m')) {
                $brochureNumber = date($dateFormat);
                $key = key($possible_month_matches);
                break;
            }
        }

        if (!isset($brochureNumber)) {
            throw new Exception($companyId . ': unable to get brochure information.');
        }

        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle('Monatsangebote')
            ->setBrochureNumber($brochureNumber)
            ->setUrl($brochureInfoListMatch[1][$key] . 'pdf/complete.pdf')
            ->setStart(date('01.m.Y', strtotime($brochureNumber)))
            ->setEnd(date('t.m.Y', strtotime($brochureNumber)))
            ->setVariety('leaflet');

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}
