<?php

/**
 * Brochure Crawler für FTI (ID: 81370)
 */

class Crawler_Company_FTI_Store extends Crawler_Generic_Company
{

    /**
     * @param int $companyId
     *
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $sGeo = new Marktjagd_Database_Service_GeoRegion();
        $baseUrl = 'https://www.meinfti.com/fti-reisebuerosuche?tx_ftimaps_search%5Baction%5D=search&tx_ftimaps_search%5Bcontroller%5D=Frontend%5CTas%5CIndex&type=932&cHash=4d0e2ca61d89a94f3b20e0dc995ca896&tx_ftimaps_search%5B__referrer%5D%5B%40extension%5D=FtiMaps&tx_ftimaps_search%5B__referrer%5D%5B%40controller%5D=Frontend%5CTas%5CDe&tx_ftimaps_search%5B__referrer%5D%5B%40action%5D=index&tx_ftimaps_search%5B__referrer%5D%5Barguments%5D=YTowOnt9ed27684e519539a8497e33371ae6806ca7f04e3d&tx_ftimaps_search%5B__referrer%5D%5B%40request%5D=%7B%22%40extension%22%3A%22FtiMaps%22%2C%22%40controller%22%3A%22Frontend%5C%5CTas%5C%5CDe%22%2C%22%40action%22%3A%22index%22%7Dbc16c826342a01e8c0e7ef1790f09566cba5a2e8&tx_ftimaps_search%5B__trustedProperties%5D=%7B%22lang%22%3A1%2C%22gridLayout%22%3A1%2C%22search%22%3A1%2C%22criterion%22%3A1%7D6c52b7bcbea8bb132173f6607bb33677f53f9a80&tx_ftimaps_search%5Blang%5D=de&tx_ftimaps_search%5BgridLayout%5D=default&tx_ftimaps_search%5Bsearch%5D=';
        $endUrl = '&tx_ftimaps_search%5Bcriterion%5D=relevance';

        $cities = [];
        foreach ($sGeo->findAll() as $eachIndividual) {
            /** @var $eachIndividual Marktjagd_Database_Entity_GeoRegion */
            if(array_key_exists($eachIndividual->getCity(), $cities)) {
                continue;
            }
            $cities[$eachIndividual->getCity()] = $eachIndividual->getCity();
        }

        $stores = [];
        foreach ($cities as $city) {
            $searchUrl = $baseUrl . strtolower($city) . $endUrl;

            try {
                $page = $sPage->getPage()->setIgnoreRobots(true);
                $sPage->open($searchUrl);
                $json = $sPage->getPage()->getResponseAsJson();
            } catch (Exception $e) {
                $this->_logger->info('skipped' . ' ' . $city);
                continue;
            }

            foreach ($json->agencies as $data) {
                if(array_key_exists($data->number, $stores)) {
                    continue;
                }

                $title = $data->name1;
                if(empty($title)) {
                    $title = $data->name2;
                    if (strlen($title) <= 5) {
                        $title = $data->name2 . ' ' . $data->name3;
                    }
                }
                if (strlen($title) <= 5) {
                    $title = $data->name1 . ' ' . $data->name2;
                }

                $stores[$data->number] = [
                    'phone'   => $data->phone,
                    'zip'     => $data->zip,
                    'city'    => $data->city,
                    'address' => $data->street,
                    'title'   => $title,
                    'email'   => $data->email,
                    'hours'   => $data->businesshours,
                ];
            }
        }

        if (empty($stores)) {
            throw new Exception('The crawler could not find any stores');
        }

        foreach ($stores as $storeNumber => $storeData) {
            $eStore = new Marktjagd_Entity_Api_Store();

            $eStore->setStoreNumber($storeNumber)
                ->setCity($storeData['city'])
                ->setTitle($storeData['title'])
                ->setPhone($storeData['phone'])
                ->setZipcode($storeData['zip'])
                ->setStreetAndStreetNumber($storeData['address'])
                ->setEmail($storeData['email'])
            ;

            $cStores->addElement($eStore);
        }

        return $this->getResponse($cStores);
    }
}
