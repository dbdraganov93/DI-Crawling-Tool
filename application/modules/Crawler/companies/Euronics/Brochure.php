<?php

class Crawler_Company_Euronics_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $aUnvStores = new Marktjagd_Service_Input_MarktjagdApi();
        $sPage = new Marktjagd_Service_Input_Page();

        $urls = [];
        foreach ($aUnvStores->findStoresByCompany($companyId)->getElements() as $store) {
            $sPage->open($store->website);
            $page = $sPage->getPage()->getResponseBody();
            if (!preg_match_all('#brochure\/([^\s]+)\/blaetterkatalog\/index.php#i', $page, $aLinks)) {
                continue;
            }

            $parsedUrl = parse_url($store->website);
            foreach ($aLinks[1] as $link) {
                $urls[$parsedUrl["host"]][$link][] = $store->storeNumber;
            }
        }

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($urls as $host => $idStores) {
            foreach ($idStores as $blaetterkatalog => $stores) {
                $eBrochure = new Marktjagd_Entity_Api_Brochure();
                $eBrochure->setUrl("https://$host/fileadmin/medien/eom/brochure/$blaetterkatalog/blaetterkatalog/blaetterkatalog/pdf/complete.pdf")
                    ->setStart(date('d.m.Y'))
                    ->setEnd(date('d.m.Y'))
                    ->setStoreNumber(implode(',', $stores))
                    ->setVariety('leaflet');
                $cBrochures->addElement($eBrochure);
            }
        }

        return $this->getResponse($cBrochures, $companyId);
    }
}
