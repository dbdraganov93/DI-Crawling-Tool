<?php

/*
 * Prospekt Crawler für Möbel Mitnahmemarkt (ID: 69741)
 */

class Crawler_Company_MoebelMit_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $aInfos = array(
            array(
                'url' => 'https://www.moebelmit.de/aktuelles/aktuelle-prospekte.html',
                'brochurePattern' => '#<p[^>]*>\s*gültig\s*vom\s*(\d{2}\.\d{2}\.?)\d{0,4}\s*-\s*(\d{2}\.\d{2}\.)(\d{4})\s*<\/p>\s*<figure[^>]*>\s*<a[^>]*href="([^"]+?pdf[^"]+?)\.html[^>]*>#i',
                'storeNumbers' => '1,2,3,4,5,6,7,8'),
            array('url' => 'https://www.naumburger-moebel-center.de/aktuelles/',
                'brochurePattern' => '#<a[^>]*href="([^"]+?prospekte[^"]+?)\.html[^>]*>#i',
                'storeNumbers' => '9')
        );

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aInfos as $aSingleInfo) {
            $count = 1;
            $sPage->open($aSingleInfo['url']);
            $page = $sPage->getPage()->getResponseBody();

            if (!preg_match($aSingleInfo['brochurePattern'], $page, $brochureInfoMatch)) {
                $this->_logger->info($companyId . ': unable to get brochure infos: ' . $aSingleInfo['url']);
                continue;
            }

            if (preg_match('#naumburger#', $aSingleInfo['url'])) {
                $escaped = preg_quote('&#8211;', '#');
                $pattern = '#(gültig|Laufzeit|gelten nur vom|gültig\s*vom):?\s*(\d{2}\.\d{2}\.?)\d{0,4}\s*(?:[^\d]+|' . $escaped . ')\s*(\d{2}\.\d{2}\.)(\d{4})#i';
                if (!preg_match($pattern, $page, $validityMatch)) {
                    $this->_logger->err($companyId . ': unable to get validity for naumburger brochure.');
                    continue;
                }

                $brochureInfoMatch[2] = $validityMatch[2];
                $brochureInfoMatch[3] = $validityMatch[3];

                if (!preg_match('#\.$#', $brochureInfoMatch[3])) {
                    $brochureInfoMatch[3] .= '.';
                }

                $brochureInfoMatch[4] = $validityMatch[4];
            }

            $localPath = $sHttp->generateLocalDownloadFolder($companyId);

            $aPdfPages = array();
            while ($count < 100) {
                $strBrochurePage = $brochureInfoMatch[4] . '/assets/common/page-html5-substrates/page' . str_pad($count, 4, '0', STR_PAD_LEFT) . '.jpg';
                if (!$sPage->checkUrlReachability($strBrochurePage)) {
                    break;
                }

                if ($sHttp->getRemoteFile($strBrochurePage, $localPath)) {
                    $sPdf->createPdf($localPath . 'page' . str_pad($count, 4, '0', STR_PAD_LEFT) . '.jpg');
                    $aPdfPages[$count] = $localPath . 'page' . str_pad($count, 4, '0', STR_PAD_LEFT) . '.pdf';
                }
                $count++;
            }

            $strCompletePdfPath = $sPdf->merge($aPdfPages, $localPath);

            if (!preg_match('#\.$#', $brochureInfoMatch[1])) {
                $brochureInfoMatch[1] .= '.';
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setUrl($sHttp->generatePublicHttpUrl($strCompletePdfPath))
                ->setTitle('Wochen Angebote')
                ->setStart($brochureInfoMatch[1] . $brochureInfoMatch[3])
                ->setEnd($brochureInfoMatch[2] . $brochureInfoMatch[3])
                ->setTags('Wohnzimmer, Küche, Schlafzimmer, Sessel, Couch, Tisch')
                ->setVariety('leaflet')
                ->setStoreNumber($aSingleInfo['storeNumbers'])
                ->setBrochureNumber('KW' . date('W', strtotime($eBrochure->getStart())) . '_'
                    . date('Y', strtotime($eBrochure->getStart())) . '_'
                    . substr(md5($aSingleInfo['storeNumbers']), 0, 10));

            $cBrochures->addElement($eBrochure);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
