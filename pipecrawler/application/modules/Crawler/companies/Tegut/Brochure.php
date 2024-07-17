<?php
/**
 * Brochure Crawler für Tegut (ID: 349)
 */

class Crawler_Company_Tegut_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPage = new Marktjagd_Service_Input_Page();

        $cStoresApi = $sApi->findStoresByCompany($companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($cStoresApi->getElements() as $eStoreApi) {
            $sPage->open($eStoreApi->getWebsite());
            $page = $sPage->getPage()->getResponseBody();

            $pattern = '#<a[^>]*href="([^"]+?\.pdf)"#';
            if (!preg_match($pattern, $page, $brochureUrlMatch)) {
                $this->_logger->info($companyId . ': unable to get brochure url: ' . $eStoreApi->getWebsite());
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Tegut: Aktuelles Flugblatt')
                ->setUrl($brochureUrlMatch[1])
                ->setStoreNumber($eStoreApi->getStoreNumber())
                ->setStart(date('d.m.Y', strtotime('monday this week')))
                ->setEnd(date('d.m.Y', strtotime('saturday this week')));

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}