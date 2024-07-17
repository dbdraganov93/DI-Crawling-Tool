<?php

class Crawler_Company_Edeka_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $week = 'next';
        $cStores = $sApi->findStoresByCompany($companyId)->getElements();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        foreach ($cStores as $eStore) {
            if (!$eStore->getWebsite()) {
                continue;
            }

            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setTitle('Wochenangebote')
                ->setStoreNumber($eStore->getStoreNumber())
                ->setUrl($eStore->getWebsite())
                ->setStart(date('d.m.Y', strtotime('monday ' . $week . ' week')))
                ->setEnd(date('d.m.Y', strtotime('saturday ' . $week . ' week')))
                ->setVisibleStart(date('d.m.Y', strtotime($eBrochure->getStart() . ' - 1 day')));

            $cBrochures->addElement($eBrochure, TRUE, 'pdf');
        }

        return $this->getResponse($cBrochures);

    }
}