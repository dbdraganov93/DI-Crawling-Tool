<?php

/**
 * Brochure Crawler für Super Biomarkt (ID: 80001)
 */

class Crawler_Company_SuperBioMarkt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.superbiomarkt.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*>\s*Angebote#';
        if (!preg_match($pattern, $page, $searchUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure search url.');
        }

        $sPage->open($searchUrlMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?indd[^"]+?)"#';
        if (!preg_match($pattern, $page, $brochureInfoUrlMatch)) {
            throw new Exception($companyId . ': unable to get brochure info url.');
        }

        $sPage->open($brochureInfoUrlMatch[1]);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#manifestBody\s*:\s*\'([^\']+?)\'#';
        if (!preg_match($pattern, $page, $brochureInfoMatch)) {
            throw new Exception($companyId . ': unable to get brochure infos.');
        }

        $jInfos = json_decode(urldecode($brochureInfoMatch[1]));

        $pattern = '#(\d{2}\.\d{2}\.)\s*.*\s*(\d{2}\.\d{2}\.\d{4})#';
        # first try - date in format "dd.mm - dd.mm.yyyy"
        if (!preg_match($pattern, $jInfos->documentDescription, $validityMatch)) {
            # second try - date in format "dd.mm - dd.mm.yy"
            $pattern = '#(\d{2}\.\d{2}\.)\s*.*\s*(\d{2}\.\d{2}\.\d{2})#';
            if (!preg_match($pattern, $jInfos->documentDescription, $validityMatch)) {
                throw new Exception($companyId . ': unable to get brochure validity.');
            }
            else {
                # if we have year in 2-digit format, insert '20' before it (works until year 3000)
                $validityMatch[2] = substr($validityMatch[2],0,strlen($validityMatch[2])-2) . '20' . substr($validityMatch[2],strlen($validityMatch[2])-2, strlen($validityMatch[2]));
            }
        }

        if (!preg_match('#\d{4}$#', $validityMatch[1])) {
            $validityMatch[1] .= date('Y');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle($jInfos->documentName)
            ->setUrl('https://adobeindd.com/view/' . $jInfos->documentPdf)
            ->setStart($validityMatch[1])
            ->setEnd($validityMatch[2])
            ->setVariety('leaflet');

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }
}