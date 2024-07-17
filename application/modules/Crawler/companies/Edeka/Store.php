<?php
/**
 * Store Crawler for all EDEKA distributions (ID: many)
 */

class Crawler_Company_Edeka_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $aCompanyIds = [
            'Nord' => '73541',
            'NST' => '69470, 69469',
            'Rhein Ruhr' => '72178, 72180',
            'Südbayern' => '72089, 72090',
            'Südwest' => '71668, 71669',
            'Hessenring' => '73681, 80197, 80196',
            'Minden Hannover' => '73682, 73684'
        ];

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($aCompanyIds as $companyName => $companyIds) {
            $aIds = preg_split('#\s*,\s*#', $companyIds);
            foreach ($aIds as $singleId) {
                $cStoresApi = $sApi->findStoresByCompany($singleId)->getElements();
                foreach ($cStoresApi as $eStoreApi) {
                    $cStores->addElement($eStoreApi);
                }
            }
        }

        return $this->getResponse($cStores);
    }
}
