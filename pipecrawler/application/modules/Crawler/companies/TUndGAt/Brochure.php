<?php

/**
 * Brochure Crawler für T&G AT (ID: 72854)
 */

class Crawler_Company_TUndGAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.tundg.at';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<a[^>]*href="([^"]+?)"[^>]*class="button[^"]*flyer-link"[^>]*>\s*([^<]+?)\s*<#';
        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        for ($i = 0; $i < count($brochureMatches[0]); $i++) {
            $sPage->open($sPage->getRedirectedUrl($brochureMatches[1][$i]));
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#PDFFile\s*:\s*\'([^\?]+?\.pdf)\?#';
            if (!preg_match($pattern, $page, $pdfMatch)) {
                $this->_logger->err($companyId . ': unable to get pdf url ' . $brochureMatches[1][$i]);
                continue;
            }
            $this->_logger->info('Found PDF - ' . $pdfMatch[1]);

            #first try - format like 'T-G_KW01_2020_Neutral.pdf'
            $pattern = '#KW(\d{2})[^\d]*(\d{2})?_(\d{4})_#';
            if (!preg_match($pattern, $pdfMatch[1], $weekMatch)) {
                #second try - format´like: 'T-G_KW2-3_2020_Ansicht_Neutral_.pdf'
                $pattern = '#KW(\d{1,2})-(\d{1,2})[^\d]*_(\d{4})_#';
                if (!preg_match($pattern, $pdfMatch[1], $weekMatch)) {
                    $this->_logger->info($companyId . ': unable to get weeks for brochure: ' . $pdfMatch[1]);
                    continue;
                }
            }

            # if it's only valid for one week - adjust the ending
            if ($weekMatch[2] == "") {
                $weekMatch[2] = $weekMatch[1];
            }

            # single weeks 1-9 need a trailing 0 (KW1 -> KW01)
            if (strlen($weekMatch[1]) == 1) {
                $weekMatch[1] = str_pad($weekMatch[1], 2, '0', STR_PAD_LEFT);
            }
            if (strlen($weekMatch[2]) == 1) {
                $weekMatch[2] = str_pad($weekMatch[2], 2, '0', STR_PAD_LEFT);
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('T&G Flugblatt')
                ->setUrl(preg_replace('#index\.html#', preg_replace('#(\[\*\,2\])#', '01', $pdfMatch[1]), $sPage->getRedirectedUrl($brochureMatches[1][$i])))
                ->setStart(date('d.m.Y', strtotime(date('Y') . '-W' . $weekMatch[1] . '-1')))
                ->setEnd(date('d.m.Y', strtotime(date('Y') . '-W' . $weekMatch[2] . '-5')))
                ->setDistribution($brochureMatches[2][$i])
                ->setVariety('leaflet')
                ->setBrochureNumber('KW' . $weekMatch[1] . $weekMatch[2] . '_' . $weekMatch[3] . '_' . $brochureMatches[2][$i]);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}