<?php
/**
 * Brochure Crawler für REPO (ID: 28830)
 */

class Crawler_Company_Repo_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sPage = new Marktjagd_Service_Input_Page();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($sApi->findStoresByCompany($companyId)->getElements() as $eStore) {
            $searchUrl = $eStore->getWebsite();
            $pdfUrls = $sPage->getUrlsFromUrl($searchUrl, '#.pdf#i', 'https://www.repo-markt.de/');
            if (!$pdfUrls) {
                $this->_logger->err("$companyId: unable to get brochure url: $searchUrl");
            }
            foreach ($pdfUrls as $pdfUrl) {
                $eBrochure = new Marktjagd_Entity_Api_Brochure();
                $eBrochure->setUrl($pdfUrl)
                    ->setBrochureNumber($this->getRandomBrochureNumber($pdfUrl))
                    ->setTitle('Wochenangebote')
                    ->setStart(date('d.m.Y', strtotime('monday this week')))
                    ->setEnd(date('d.m.Y', strtotime('saturday this week')))
                    ->setVariety('leaflet')
                    ->setStoreNumber($eStore->getStoreNumber());

                $cBrochures->addElement($eBrochure);
            }
        }
        return $this->getResponse($cBrochures, $companyId);
    }
}