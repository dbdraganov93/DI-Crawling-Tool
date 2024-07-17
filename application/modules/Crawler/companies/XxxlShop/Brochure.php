<?php

/*
 * Prospekt Crawler für XXXLutz (ID: 80)
 */

class Crawler_Company_XxxlShop_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId)
    {
        $data = '{"operationName":"brochures","variables":{},"query":"query brochures {\n  getBrochures {\n    brochureTypes {\n      name\n      seoCode\n      id\n      brochures {\n        id\n        title\n        startDate\n        endDate\n        downloadUrl\n        image {\n          cdnFilename\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $url = 'https://www.xxxlutz.de/api/graphql';

        $sPage = new Marktjagd_Service_Input_Page();
        $jData = $sPage->getJsonFromGraphQL($url, $data);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($jData->data->getBrochures->brochureTypes as $aJBrochures) {
            if (!preg_match('#aktuelle\s*prospekte#i', $aJBrochures->name)) {
                continue;
            }

            foreach ($aJBrochures->brochures as $singleJBrochure) {
                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setBrochureNumber(substr($singleJBrochure->id,0,32))
                    ->setStart($singleJBrochure->startDate)
                    ->setEnd($singleJBrochure->endDate)
                    ->setVisibleStart($singleJBrochure->startDate)
                    ->setVisibleEnd($singleJBrochure->endDate)
                    ->setUrl($this->getLeafletWithClickout($singleJBrochure->downloadUrl . '/' . $singleJBrochure->image->cdnFilename, $companyId))
                    ->setVariety('leaflet')
                    ->setTitle($singleJBrochure->title);

                if (!strlen($eBrochure->getTitle())) {
                    $eBrochure->setTitle('XXXLutz : Möbelangebote');
                }

                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }


    /**
     * @param string $url
     * @param string $companyId
     * @return string
     * @throws Zend_Exception
     */
    private function getLeafletWithClickout(string $url, string $companyId): string
    {
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $localUrl = $sHttp->getRemoteFile($url, $sHttp->generateLocalDownloadFolder($companyId), date('YmdHim') . '.pdf');
        $coords = [
            [
                'width' => 100,
                'height' => 100,
                'page' => 0,
                'startX' => 45,
                'startY' => 65,
                'endX' => 55,
                'endY' => 75,
                'link' => 'https://www.xxxlutz.de/',
            ],
        ];

        return $sPdf->setAnnotations($localUrl, $sPdf->getJsonCoordinatesFile($coords));
    }

}
