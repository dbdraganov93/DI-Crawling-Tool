<?php
/**
 * Store Crawler für Pagro (ID: 72313)
 */

class Crawler_Company_Pagro_Store extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.pagro.de/';
        $searchUrl = $baseUrl . 'maerkte/';
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();

        $stores = $sPage->getDomElsFromUrl($searchUrl, ['background-color: #;', 'background-color: #f6f6f6;'], 'style', 'tr');
        foreach ($stores as $store) {
            $storeAttributes = $this->getStoreAttributes($store->textContent);

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setStoreHoursNormalized($storeAttributes['opening'])
                ->setZipcodeAndCity($storeAttributes['plzAndCity'])
                ->setStreetAndStreetNumber($storeAttributes['streetAndNumber']);

            $cStores->addElement($eStore);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvStore($companyId);
        $fileName = $sCsv->generateCsvByCollection($cStores);
        return $this->_response->generateResponseByFileName($fileName);
    }

    /**
     * @param string $storeRawInfo
     * @return array
     */
    private function getStoreAttributes($storeRawInfo)
    {
        $storeInfo = str_replace('?', '-', $storeRawInfo);
        $storeSplitInfo = preg_split('#[^\s]+ungszeiten:#i', $storeInfo);
        $storeAddress = preg_split("#[\r|\n]#", trim($storeSplitInfo[0]));

        return [
            'opening' => preg_replace(['#Uhr[\r|\n]#', '#[\r|\n]#'], ['Uhr, ', ': '], trim($storeSplitInfo[1])),
            'plzAndCity' => $storeAddress[0],
            'streetAndNumber' => end($storeAddress),
        ];
    }
}