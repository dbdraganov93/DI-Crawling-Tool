<?php

/**
 * Brochure Crawler für Lidl AT (ID: 73217)
 */
class Crawler_Company_LidlAt_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://endpoints.lidl-flyer.com/';
        $searchUrl = $baseUrl . 'v1/overview/de-AT.json';
        $sPage = new Marktjagd_Service_Input_Page();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cStores = $sApi->findStoresByCompany($companyId);

        $sPage->open($searchUrl);
        $jInfos = $sPage->getPage()->getResponseAsJson();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($jInfos->categories as $singleJBrochureCategory) {
            if (!preg_match('#Wochenaktionen#i', $singleJBrochureCategory->name)) {
                continue;
            }
            foreach ($singleJBrochureCategory->subcategories as $singleJSubCategory) {
                foreach ($singleJSubCategory->flyers as $singleJBrochure) {
                    $strDists = '';
                    foreach ($singleJBrochure->regions as $singleRegion) {
                        if (strlen($strDists)) {
                            $strDists .= ',';
                        }
                        $strDists .= $singleRegion->code;
                    }

                    $eBrochure = new Marktjagd_Entity_Api_Brochure();

                    $eBrochure->setTitle($singleJBrochure->title)
                        ->setVisibleStart($singleJBrochure->startDate)
                        ->setStart($singleJBrochure->offerStartDate)
                        ->setEnd($singleJBrochure->endDate)
                        ->setUrl($singleJBrochure->pdfUrl)
                        ->setVariety('leaflet')
                        ->setBrochureNumber(substr($singleJBrochure->id, 0, 20))
                        ->setDistribution($strDists);

                    $cBrochures->addElement($eBrochure);
                }
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
