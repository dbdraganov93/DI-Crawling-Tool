<?php

/*
 * Brochure Crawler für Begros (ID: 71814)
 */

class Crawler_Company_Begros_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'http://www.mondo-moebel.de/';
        $sPage = new Marktjagd_Service_Input_Page();

        $sPage->open($baseUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#li\s*class="first"[^>]*>(.+?)</ul#';
        if (!preg_match($pattern, $page, $urlListMatch))
        {
            throw new Exception($companyId . ': unable to get brochure url list.');
        }

        $pattern = '#href="([^"]+?)\/"\s*title="([^"]+?)"#';
        if (!preg_match_all($pattern, $urlListMatch[1], $detailUrlMatches))
        {
            throw new Exception($companyId . ': unable to get any brochure urls from list.');
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $aBrochures = array();
        for ($i = 0; $i < count($detailUrlMatches[1]); $i++)
        {
            $sPage->open($baseUrl . $detailUrlMatches[1][$i]);
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#href="[^\/]+?\/(details[^"]+?)"[^>]*>.+?<h1[^>]*>\s*([^<]+?)\s*<#';
            if (!preg_match_all($pattern, $page, $brochureDetailUrlMatches))
            {
                $this->_logger->err($companyId . ': unable to get any detail urls for:' . $baseUrl . $detailUrlMatches[1][$i]);
            }

            for ($j = 0; $j < count($brochureDetailUrlMatches[1]); $j++)
            {
                $sPage->open($baseUrl . $detailUrlMatches[1][$i] . '/' . $brochureDetailUrlMatches[1][$j]);
                $page = $sPage->getPage()->getResponseBody();

                $pattern = '#href="([^"]+?\/([^\/]+?\.pdf))"[^>]*>\s*Brosch#i';
                if (!preg_match($pattern, $page, $brochureUrlMatch))
                {
                    $this->_logger->info($companyId . ': unable to get detail url for: ' . $baseUrl . $detailUrlMatches[1][$i] . '/' . $brochureDetailUrlMatches[1][$j]);
                    continue;
                }
                if (!in_array($brochureUrlMatch[2], $aBrochures))
                {
                    $aBrochures[] = $brochureUrlMatch[2];
                    $eBrochure = new Marktjagd_Entity_Api_Brochure();
                    $eBrochure->setTitle(preg_replace('#ue#', 'ü', ucwords($detailUrlMatches[1][$i]) . ' Angebote'))
                            ->setUrl($baseUrl . $brochureUrlMatch[1])
                            ->setTags(preg_replace(array('#\s*und\s*#', '#\s*von\s*MONDO#'), array(', ', ''), $detailUrlMatches[2][$i]))
                            ->setVariety('leaflet');

                    $cBrochures->addElement($eBrochure);
                } else
                {
                    continue;
                }
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
