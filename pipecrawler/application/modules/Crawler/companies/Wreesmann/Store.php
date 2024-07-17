<?php

/**
 * Store Crawler für Wreesmann (ID: 68891)
 */
class Crawler_Company_Wreesmann_Store extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $searchUrl = 'http://www.aktionshaus-wreesmann.de/index.php/standorte-liste';

        foreach ($sPage->getResponseAsDOM($searchUrl)->getElementsByTagName('tr') as $tr) {
            if ($tr->getAttribute('id') == "table_title") {
                continue;
            }
            $tds = $tr->getElementsByTagName('td');


            $eStore = new Marktjagd_Entity_Api_Store;
            $eStore->setCity($tds[3]->textContent)
                ->setZipcode($tds[2]->textContent)
                ->setStreetAndStreetNumber($tds[1]->textContent)
                ->setText('Marktleiter(in): ' . $tds[4]->textContent)
                ->setStoreHoursNormalized($tds[5]->textContent);

            if (!preg_match('#geöffnet#', $tds[6]->textContent)) {
                $eStore->setStoreHoursNotes($tds[6]->textContent);
            }
            if ($eStore->getCity() == 'Wanzleben')
            {
                $eStore->setCity('Wanzleben-Börde');
            }

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores, $companyId);
    }
}
