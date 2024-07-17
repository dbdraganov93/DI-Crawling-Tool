<?php

require_once APPLICATION_PATH . '/../vendor/autoload.php';
require APPLICATION_PATH . '/../library/Marktjagd/Service/Yext/YextApi.php';

class Crawler_Company_UberallYext_StoreReceipt extends Crawler_Generic_Company
{
    private array $yestData;
    private array $companyStores;
    private Marktjagd_Service_Input_MarktjagdApi $api;
    private const SEPARATOR = "\r\n";
    private const MAX_SLUG_LENGTH = 1950;
    private const COUNTRY_FOLDERS = [
        'DE' => [
            'country' => 'de',
            'folder' => 'marktjagd_de_feeds',
            'url' => 'https://www.handelsangebote.de/f/',
            'companies' => [
                14 => 'Media Markt / Saturn',
                27 => 'dm-drogerie markt',
                29 => 'ALDI Süd',
                60 => 'HORNBACH Baumarkt AG',
                28895 => 'Vodafone',
                69216 => 'OBI',
            ]
        ],
        'AT' => [
            'country' => 'at',
            'folder' => 'wogibtswas_at_feeds',
            'url' => 'https://www.wogibtswas.at/f/',
            'companies' => [
                73424 => 'dm drogerie markt',
                77600 => 'Unser Lagerhaus WarenhandelsgesmbH',
                72982 => 'Hofer',
                73687 => 'Hammerl Textilreinigung GmbH',
                72750 => 'ElectronicPartner',
                73388 => 'John Harris',
                88291 => 'Lieb Markt GesmbH',
//                464356 => 'RED ZAC - Bauer Electronic GmbH',
//                72492 => 'Red Zac Mikes Inh. Michael Scherwitzl',
//                571845 => 'Red Zac Jeitler',
//                590971 => 'Red ZacPauli, Paul Duhanaj e.U.',
                73321 => 'OBI',
                72718 => 'HORNBACH',
            ]
        ],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->api = new Marktjagd_Service_Input_MarktjagdApi();
    }

    public function crawl($companyId)
    {
        $stores = new Marktjagd_Collection_Api_Store();
        foreach (self::COUNTRY_FOLDERS as $companyData) {

            $this->yestData = $companyData;
            $this->companyStores = $this->getCompanyStores();

            $yextApi = new YextApi($companyId, 'production', $this->yestData['folder']);
            $response = $yextApi->getStores($this->yestData['folder'] . '/listings_' . date("Y-m-d", strtotime("yesterday")) . '.json', []);

            if ($response['http_code'] != 200) {
                $this->_logger->err($response['error_message']);
                continue;
            }

            $storesData = $this->getStoresData($response['body']);

            $receipt = '';
            foreach ($storesData as $store) {
                $receipt = $receipt . $this->createReceiptEntry($store) . PHP_EOL;
            }

            $yextApi->uploadReceipt($receipt, $companyId, $stores);
        }

        return $this->getSuccessResponse();
    }

    private function getStoresData(string $yextFeed): array
    {
        $line = strtok($yextFeed, self::SEPARATOR);

        $storesData = [];
        while ($line !== false) {
            $store = json_decode($line, true);
            array_push($storesData, $store);
            $line = strtok(self::SEPARATOR );
        }

        if (0 == count($storesData)) {
            throw new Exception('ERROR: No stores to map');
        }

        return $storesData;
    }

    private function getCompanyStores(): array
    {
        $companyStores = [];
        foreach ($this->yestData['companies'] as $companyId => $companyName) {
            $companyStores[$companyId] = $this->api->findStoresByCompany($companyId)->getElements();
        }

        return $companyStores;
    }

    private function findStore(array $yextStore): ?Marktjagd_Entity_Api_Store
    {
        foreach ($this->yestData['companies'] as $companyId => $companyName) {
            if (!stristr($yextStore['name'], $companyName)) {
                continue;
            }

            foreach ($this->companyStores[$companyId] as $store) {
                if (
                    $yextStore['address']['address'] == $store->getStreet() . ' ' . $store->getStreetNumber() &&
                    $yextStore['address']['postalCode'] == $store->getZipcode() &&
                    $yextStore['address']['city'] == $store->getCity()
                ) {
                    return $store;
                }
            }
        }

        return null;
    }

    private function createReceiptEntry(array $yextStore)
    {
        $store = $this->findStore($yextStore);

        $receiptEntry = [];
        $receiptEntry['yextId'] = $yextStore['yextId'];
        $receiptEntry['partnerId'] = $store != null ? $store->getId() : '';
        $receiptEntry['status'] = $store != null ? 'LIVE' : 'REJECTED';

        if (!$store) {
            $receiptEntry['issues'][] = ['description' => 'Not a mutual customer'];
        }

        $receiptEntry['url'] = $store != null ? $this->yestData['url'] . $this->slugifyStore($store) : '';

        return json_encode($receiptEntry);
    }

    private function slugifyStore(Marktjagd_Entity_Api_Store $store): string
    {
        return $this->slugify(\sprintf('%s-%s-%s-%s-%s-%s', $store->getId(), $store->getTitle(), $store->getStreet(), $store->getStreetNumber() ?? null, $store->getZipcode(), $store->getCity()));
    }

    public function slugify(string $title): string
    {
        $systemLocale = \setlocale(LC_ALL, 0);
        \setlocale(LC_ALL, $this->locale);

        $slug = \iconv('UTF-8', 'ASCII//TRANSLIT', $title);
        $slug = \preg_replace('/[^A-Za-z0-9-]+/', '-', $slug);
        $slug = \preg_replace('/-+/', '-', $slug);
        $slug = \trim($slug, '-');
        $slug = \strtolower($slug);

        \setlocale(LC_ALL, $systemLocale);

        return \substr($slug, 0, self::MAX_SLUG_LENGTH);
    }
}
