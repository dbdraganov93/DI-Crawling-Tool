<?php
/**
 * Brochure Crawler für TopCC CH (ID: 72322)
 */

class Crawler_Company_TopCcCh_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.topcc.ch/';
        $aSearchUrls = array(
            $baseUrl . 'sortiment/wochen-hits/wochen-hits-kommende-woche/',
            $baseUrl . 'sortiment/wochen-hits/wochen-hits-laufende-woche/');
        $sPage = new Marktjagd_Service_Input_Page();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aSearchUrls as $searchUrl) {
            $sPage->open($searchUrl);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="([^"]+?\.pdf)"#si';
            if (!preg_match($pattern, $page, $brochureUrlMatch)) {
                $this->_logger->info($companyId . ': unable to get pdf url: ' . $searchUrl);
                continue;
            }

            $strTitle = 'TopCC clever & charmant - immer für Sie da!';
            if (preg_match('#beilagen#', $searchUrl)) {
                $pattern = '#<h2[^>]*>\s*([^<]+?)\s*<\/h2>\s*<p[^>]*>.*<\/p>\s*<p[^>]*>\s*download#is';
                if (!preg_match($pattern, $page, $titleMatch)) {
                    $this->_logger->err($companyId . ': unable to get brochure title: ' . $searchUrl);
                    continue;
                }
                $strTitle = $titleMatch[1];
            }

            $pattern = '#gültig\s*(von|ab)\s*([^\s]+?)\s*bis\s*([^<]+?)(\d{4})#i';
            if (!preg_match($pattern, $page, $validityMatch)) {
                throw new Exception($companyId . ': unable to get brochure validity.');
            }

            $strStart = $validityMatch[2];
            if (!preg_match('#\d{4}$#', $strStart)) {
                $strStart .= $validityMatch[4];
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle($strTitle)
                ->setUrl($baseUrl . trim($brochureUrlMatch[1], '/'))
                ->setStart($strStart)
                ->setEnd($validityMatch[3] . $validityMatch[4])
                ->setVariety('customer_magazine')
                ->setDistribution('de')
                ->setBrochureNumber('KW' . date('W') . '_' . date('Y') . '_de')
                ->setLanguageCode('de');

            $cBrochures->addElement($eBrochure);
        }

        $searchUrl = $baseUrl . 'sortiment/wochen-hits/wochen-hits-beilagen/';

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<h1[^>]*class="m-section__title[^>]*>(.+?)<\/div>\s*<\/div>#is';
        if (preg_match_all($pattern, $page, $extraBrochureMatches)) {
            $this->_logger->info($companyId . ': extra brochures available.');

            foreach ($extraBrochureMatches[1] as $singleBrochure) {
                if (!preg_match('#a[^>]*href="\/([^"]+?\.pdf)"#', $singleBrochure, $pdfMatch)) {
                    continue;
                }

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $pattern = '#^\s*([^<]+?)\s*<#';
                if (preg_match($pattern, $singleBrochure, $titleMatch)) {
                    $eBrochure->setTitle($titleMatch[1]);
                }

                $pattern = '#gültig\s*von\s*([^\s]+?)\s*bis\s*([^<]+?)(\d{4})\s*<#i';
                if (preg_match($pattern, $singleBrochure, $validityMatch)) {
                    $eBrochure->setStart($validityMatch[1] . $validityMatch[3])
                        ->setEnd($validityMatch[2] . $validityMatch[3])
                        ->setVisibleStart($eBrochure->getStart());
                }

                $eBrochure->setUrl($baseUrl . $pdfMatch[1])
                    ->setVariety('customer_magazine')
                    ->setLanguageCode('DE');

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}