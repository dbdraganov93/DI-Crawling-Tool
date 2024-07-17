<?php

/**
 * Brochure Crawler for Mömax (ID: 68888)
 *
 */

class Crawler_Company_Moemax_Brochure extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $searchUrl = 'https://www.moemax.de/api/graphql?opname=brochures';
        $postParams = array(
            "operationName" => "brochures",
            "query" => "query brochures {
                getBrochures {
                    brochureTypes {
                        name
                        seoCode
                        id
                        brochures {
                            id
                            title
                            startDate
                            endDate
                            downloadUrl
                            image {
                                cdnFilename
                                __typename
                            }
                            __typename
                        }
                        __typename
                    }
                    __typename
                }}"
            );

        $sPage = new Marktjagd_Service_Input_Page();
        $responseBody = $sPage->getJsonFromGraphQL($searchUrl, json_encode($postParams));

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($responseBody->data->getBrochures->brochureTypes as $jsonObject) {
            if (preg_match('#aktuelle\s*prospekte#i', $jsonObject->name)) {
                foreach ($jsonObject->brochures as $brochure) {
                    if (preg_match('#online#i', $brochure->title)) {
                        continue;
                    }
                    $eBrochure = new Marktjagd_Entity_Api_Brochure();
                    $eBrochure->setBrochureNumber($brochure->id)
                            ->setTitle('MömaX: ' . $brochure->title)
                            ->setUrl($this->getLeafletWithClickout($brochure->downloadUrl . '/' . $brochure->image->cdnFilename, $companyId))
                            ->setStart(date_format(date_create($brochure->startDate), "d.m.Y"))
                            ->setEnd(date_format(date_create($brochure->endDate), "d.m.Y"))
                            ->setVisibleStart($eBrochure->getStart())
                            ->setVariety('leaflet');
                    $cBrochures->addElement($eBrochure);
                }
                break;
            }
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvBrochure($companyId);
        $fileName = $sCsv->generateCsvByCollection($cBrochures);

        return $this->_response->generateResponseByFileName($fileName);
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
                'link' => 'https://www.moemax.de/',
            ],
        ];
        return $sPdf->setAnnotations($localUrl, $sPdf->getJsonCoordinatesFile($coords));
    }
}
