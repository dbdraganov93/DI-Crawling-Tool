<?php

/**
 * Brochure Crawler für Adeg AT (ID: 72774)
 */

class Crawler_Company_AdegAt_Brochure extends Crawler_Generic_Company
{


    public function crawl($companyId)
    {
        $baseUrl = 'https://www.adeg.at/';
        $searchUrl= 'aktionen/flugblaetter-angebote/adeg-flugblatt';
        $sPage = new Marktjagd_Service_Input_Page();
        $sHttp = new Marktjagd_Service_Transfer_Http();


        $sPage->open($baseUrl . $searchUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#data-federal-state=\"(\w+)\"><a href="https:\/\/issuu\.com\/plan2net\/docs\/([\w_]+kw\d{1,2}.*?)\?#';

        if (!preg_match_all($pattern, $page, $brochureMatches)) {
            throw new Exception($companyId . ': unable to get any brochures.');
        }

        $infoArray = [];

        foreach ($brochureMatches[2] as $index => $url)
        {
            $infoArray[$url] = $brochureMatches[1][$index];
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($infoArray as $pictureUrl => $state) {

            $sPage = new Marktjagd_Service_Input_Page();
            $s3 = new Marktjagd_Service_Output_S3File('di',"Adeg:_$pictureUrl");

            $pictureJson = $this->getBrochureJson($pictureUrl, $sPage);
            $pictures = $this->getBrochureImages($pictureJson);

            $sPdf = new Marktjagd_Service_Output_Pdf();
            $sHttp = new Marktjagd_Service_Transfer_Http();

            $localPath = $sHttp->generateLocalDownloadFolder($pictureUrl);
            $localPdfs = [];
            $localFiles = $this->getLocalFiles($pictures, $localPath, $sHttp);
            foreach ($this->getLocalFiles($localFiles, $localPath, $sHttp) as $page => $localBrochureImage) {
                $sPdf->createPdf($localBrochureImage);
                $localPdfs[$page] = $this->getFileWithNewExt($localBrochureImage, 'pdf');
            }
            ksort($localPdfs);
            $localPdfFile = $s3->saveFileInS3($sPdf->merge($localPdfs, $localPath));

            $typeOfBrochure = [
                'stamm' => 'Aktivmarkt',
                'nv' => 'Nahversorger',
            ];

            foreach ($typeOfBrochure as $abbreviation => $longVersion)
            {
                preg_match('#' . $abbreviation . '#i', $pictureUrl, $whichMarket);
            }
            $market = $typeOfBrochure[$whichMarket[0]];
            if ($state == 'main')
            {
                $state = '';
            }
            $state = ucfirst($state);

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle("Adeg: $market Angebote $state")
                ->setUrl($localPdfFile)
                ->setBrochureNumber('KW' . date('W') . '_' . date('y') . '_' . $state . '_' . $market)
                ->setStart(date('d.m.Y', strtotime('monday this week')))
                ->setEnd(date('d.m.Y', strtotime('saturday this week')))
                ->setVariety('leaflet');
            if ($state != 'main')
            {
                $eBrochure->setDistribution($state);
            }


            $cBrochures->addElement($eBrochure);

        }

        return $this->getResponse($cBrochures, $companyId);
    }
    private function getBrochureJson($brochureId, $sPage)
    {
        //reader3.isu.pub/plan2net/aktiv_stamm_adeg_fb_kw42/reader3_4.json
        $url = strtr('https://reader3.isu.pub/plan2net/%%DOCUMENT_URI%%/reader3_4.json', [
            '%%DOCUMENT_URI%%' => $brochureId,
        ]);
        $sPage->open($url);
        $brochureJson = $sPage->getPage()->getResponseAsJson();
        if (empty($brochureJson))
        {
            throw new Exception($brochureId . ': unable to get any pictures.');
        }

        return $brochureJson;
    }

    /**
     * @param object $brochureJson
     * @return array
     * @throws Zend_Exception
     */
    private function getBrochureImages($brochureJson)
    {
        $brochureImages = [];
        if (!isset($brochureJson->document->pages)) {
            Zend_Registry::get('logger')->err("The Structure of the Json has changed");
            return $brochureImages;
        }
        $count = 0;
        foreach ($brochureJson->document->pages as $page) {
            $index = ++$count;
            if (!isset($page->imageUri) || !filter_var($url = "http://$page->imageUri", FILTER_VALIDATE_URL)) {
                Zend_Registry::get('logger')->err("The URL http://$page->imageUri for Page $count is not valid");
                continue;
            }
            if (preg_match('#page_(\d+)#', $page->imageUri, $pageNr)) {
                $index = $pageNr[1];
            }
            $brochureImages[$index] = "http://$page->imageUri";
        }
        return $brochureImages;
    }

    /**
     * @param array $brochureImages
     * @param string $localPath
     * @param Marktjagd_Service_Transfer_Http as $sHttp
     * @return array
     */
    private function getLocalFiles($brochureImages, $localPath, $sHttp)
    {
        $aPages = [];
        $count = 0;
        foreach ($brochureImages as $brochureImage) {
            $sHttp->getRemoteFile($brochureImage, $localPath);
        }
        foreach (scandir($localPath) as $pagePath) {
            $index = ++$count;
            if (preg_match('#page_(\d+)#', $pagePath, $pageNr)) {
                $index = $pageNr[1];
            }
            $aPages[$index] = $localPath . $pagePath;
        }
        return $aPages;
    }
    /**
     * @param string $file
     * @param string $newExt
     * @return string
     */
    private function getFileWithNewExt($file, $newExt)
    {
        $info = pathinfo($file);
        return ($info['dirname'] ? $info['dirname'] . DIRECTORY_SEPARATOR : '')
            . $info['filename']
            . '.'
            . $newExt;
    }
}