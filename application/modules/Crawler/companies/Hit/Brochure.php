<?php

/* 
 * Brochure Crawler für HIT (ID: 58)
 */

class Crawler_Company_Hit_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.hit.de/';
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();
        $cStores = $sApi->findStoresByCompany($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cStores->getElements() as $eStore) {
            if($eStore->getStoreNumber() != '72')
                continue;

            $brochureUrl = preg_replace('#/([^/]+)\.html$#', '/handzettel-$1/aktuell.html', $eStore->getWebsite());

            $ch = curl_init($brochureUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_COOKIE, 'store-my=' . $eStore->getStoreNumber());
            $page = curl_exec($ch);
            curl_close($ch);

            $pattern = '#<a[^>]*href="/([^"]+?\.pdf)"#';
            if (!preg_match($pattern, $page, $pdfUrlMatch)) {
                $this->_logger->info($companyId . ': no brochure for this week for ' . $eStore->getWebsite());
                continue;
            }

            $pattern = '#KW' . date('W') . '/' . $sTimes->getWeeksYear()
                . '</span>\s*<br[^>]*>[^<]+?<br[^>]*>\s*([^-]+?)\s*-\s*([^<]+?)\s*<#s';

            if (!preg_match($pattern, $page, $validityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure validity: ' . $eStore->getWebsite());
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('HIT: Wochenangebote')
                ->setUrl($baseUrl . $pdfUrlMatch[1])
                ->setStart($validityMatch[1] . $sTimes->getWeeksYear())
                ->setEnd(date('d.m.Y', strtotime($validityMatch[2] . '- 1 day')))
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . '- 1 day')))
                ->setStoreNumber($eStore->getStoreNumber())
                ->setVariety('leaflet');

            $cBrochures->addElement($eBrochure, TRUE);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}