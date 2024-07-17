<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/Yext/YextApi.php';

/**
 * Store Crawler for DM (ID: 27, 73424)
 */
class Crawler_Company_Dm_YextStore extends Crawler_Generic_Company
{
    private $yextData = [
        27 => [
            'name' => 'dm-drogerie markt',
            'folder' => 'marktjagd_de_feeds',
            'country' => 'DE',
        ],
        73424 => [
            'name' => 'dm drogerie markt',
            'folder' => 'wogibtswas_at_feeds',
            'country' => 'AT',
        ],
    ];

    public function crawl($companyId)
    {
        $this->_logger->info('fetching stores from Yext s3 data feed');
        $config = $this->yextData[$companyId];
        $sYextApi = new YextApi($companyId, 'production', $config['folder']);
        $response = $sYextApi->getStores(
            $config['folder'] . '/listings_' . date("Y-m-d", strtotime("yesterday")) . '.json',
            ['name' => [
                "#{$config['name']}#i"
            ]]
        );

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('error during yext api call');
        }

        $ret = $sYextApi->mapYextStoreData($response['body']);

        $cStores = new Marktjagd_Collection_Api_Store();
        foreach ($ret['cStores']->getElements() as $singleStore) {
            $singleStore->setTitle(null);
            $singleStore->setEmail(null);
            $cStores->addElement($singleStore);
        }

//        This is the return value with receipt creation
//        return $sYextApi->importYextStoresAndCreateReceipt($ret['yextIds'], $ret['cStores'], $this->getResponse($ret['cStores'], $companyId), 115, 'DE');

//        This is the return value without receipt creation
        return $this->getResponse($cStores, $companyId);
    }
}
