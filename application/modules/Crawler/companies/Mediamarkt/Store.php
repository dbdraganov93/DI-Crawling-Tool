<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/Yext/YextApi.php';
/**
 * Store Crawler für Media Markt (ID: 14)
 */
class Crawler_Company_Mediamarkt_Store extends Crawler_Generic_Company
{
    // the company name is used to search for stores. This will be removed after the mediaMarc finishes merging the stores.
    const COMAPNY_NAME = ['#MediaMarkt#i', '#Saturn#i',];

    // https://di-gui.offerista.com/Crawler/crawler/crawlerdetail/idCrawlerConfig/1494
    const CRAWLER_CONFIG_ID = 1494;

    public function crawl($companyId)
    {
        $yextApi = new YextApi($companyId, 'production', 'marktjagd_de_feeds');
        foreach (self::COMAPNY_NAME as $name) {
            $ret = $this->getStoresByName($yextApi, $name);

            if (!empty($stores)) {
                foreach ($ret['cStores']->getElements() as $store) {
                    $stores->addElement($store);
                }
            } else {
                $stores = $ret['cStores'];
            }
            $yextIds = !empty($yextIds) ? array_merge($yextIds, $ret['yextIds']) : $ret['yextIds'];
        }

        return $yextApi->importYextStoresAndCreateReceipt($yextIds, $stores, $this->getResponse($stores, $companyId),self::CRAWLER_CONFIG_ID, 'AT');
    }

    /**
     * @throws Zend_Exception
     */
    private function getStoresByName(YextApi $sYextApi, string $name): array
    {
        $response = $sYextApi->getStores(
            'marktjagd_de_feeds/listings_' . date("Y-m-d", strtotime("yesterday")) . '.json',
            ['name' => [$name]]
        );

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('error during yext api call');
        } elseif (empty($response['body'])) {
            throw new Exception('no results in yext api call');
        }

        return $sYextApi->mapYextStoreData($response['body']);
    }
}
