<?php
/**
 * Brochure crawler for Metro AT (ID: 72951)
 */

class Crawler_Company_MetroAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.metro.at/';
        $searchUrl = $baseUrl . 'aktuelle-angebote/metro-post';
        $brochureInfoUrl = 'https://api.publitas.com/v1/groups/metro-osterreich/publications.json';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localPath = $sHttp->generateLocalDownloadFolder($companyId);

        $sPage->open($searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<strong[^>]*>\s*([^<]+?)\s*<\/strong>\s*<span[^>]*>\s*(\d{2}\.\d{2}\.\d{4})\s*\-\s*(\d{2}\.\d{2}\.\d{4})\s*<\/span>#';
        if (!preg_match_all($pattern, $page, $validityMatches)) {
            throw new Exception($companyId . ': unable to get validities.');
        }

        $aValidities = [];
        for ($i = 0; $i < count($validityMatches[0]); $i++) {
            $aValidities[$validityMatches[1][$i]] = [
                'valid_start' => $validityMatches[2][$i],
                'valid_end' => $validityMatches[3][$i]
            ];
        }

        $aClickOuts = [
            [
                'page' => 0,
                'link' => 'https://www.metro.at/infos-fuer-metro-kunden/newsletter',
                'startX' => 17.2698,
                'startY' => 675.324,
                'endX' => 100.892,
                'endY' => 721.679,
                'width' => 510.2353,
                'height' => 788.0315
            ],
            [
                'page' => 0,
                'link' => 'https://www.metro.at/aktuelle-angebote/metro-post',
                'startX' => 229.052,
                'startY' => 402.643,
                'endX' => 301.052,
                'endY' => 443.361,
                'width' => 510.2353,
                'height' => 788.0315
            ],
            [
                'page' => 0,
                'link' => 'https://www.metro.at/metro-kunde-werden',
                'startX' => 233.596,
                'startY' => 21.6158,
                'endX' => 305.596,
                'endY' => 47.2499,
                'width' => 510.2353,
                'height' => 788.0315
            ]
        ];

        $jFilePath = $localPath . $companyId . '.json';
        $fh = fopen($jFilePath, 'w+');
        fwrite($fh, json_encode($aClickOuts));
        fclose($fh);

        $sPage->open($brochureInfoUrl);
        $jInfos = $sPage->getPage()->getResponseAsJson();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($jInfos as $singleJBrochure) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setBrochureNumber($singleJBrochure->id)
                ->setStart($aValidities[trim($singleJBrochure->title)]['valid_start'])
                ->setEnd($aValidities[trim($singleJBrochure->title)]['valid_end'])
                ->setTitle(trim($singleJBrochure->title))
                ->setVariety('leaflet');

            if (!strlen($eBrochure->getStart())
                || !strlen($eBrochure->getEnd())) {
                continue;
            }

            $sPage->open($singleJBrochure->url);
            $jDetailInfos = $sPage->getPage()->getResponseAsJson();

            $localBrochure = $sHttp->getRemoteFile('https://aktionen.metro.at' . $jDetailInfos->config->downloadPdfUrl, $localPath);
            $filePath = $sPdf->setAnnotations($localBrochure, $jFilePath);

            $eBrochure->setUrl($filePath);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}