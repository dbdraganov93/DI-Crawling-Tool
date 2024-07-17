<?php
/**
 * Brochure Crawler für E. Leclerc FR (ID: 72314)
 */

class Crawler_Company_ELeclercFr_Brochure extends Crawler_Generic_Company
{
    private const SEARCH_URL_BASE = 'https://www.e.leclerc/api/rest/elpev-api/list?filters=';
    private const SEARCH_URL_PARAMS = '&page=1&size=20';
    private const DATE_FORMAT = 'd.m.Y';
    private const DOWNLOAD_URL_BASE = 'https://nos-catalogues-promos-v2-api.e.leclerc/';
    private const DOWNLOAD_URL_ORIGIN = 'https://nos-catalogues-promos-v2.e.leclerc';

    private Marktjagd_Service_Transfer_Http $sHttp;
    private array $brochuresToImport;

    public function __construct()
    {
        parent::__construct();
        $this->sHttp = new Marktjagd_Service_Transfer_Http();
    }

    public function crawl($companyId)
    {
        $this->localPath = $this->sHttp->generateLocalDownloadFolder($companyId);

        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $cStores = $sApi->findStoresByCompany($companyId);

        $this->findBrochuresToImport($cStores, $companyId);

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($this->brochuresToImport as $data) {
            $eBrochure = $this->generateBrochure($data);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    private function findBrochuresToImport(object $cStores, int $companyId): void
    {
        $sPage = new Marktjagd_Service_Input_Page();

        foreach ($cStores->getElements() as $eStore) {
            $this->_logger->info($companyId . ': checking brochures for ' . $eStore->getStoreNumber());

            $url = $this->buildSearchUrl($eStore->getStoreNumber());
            $sPage->open($url);
            $jInfos = $sPage->getPage()->getResponseAsJson();

            if (empty($jInfos->items)) {
                continue;
            }

            foreach ($jInfos->items as $item) {
                if ($item->isActive) {
                    $this->addBrochureIfNotExist($item);
                }
            }
        }
    }

    private function buildSearchUrl(int $storeNumber): string
    {
        $filter = [
            "type" => [
                "value" => "01"
            ],
            "storePanonceauCode" => [
                "value" => "{$storeNumber}"
            ]
        ];
        $encoded = json_encode($filter);
        $urlEncoded = urlencode($encoded);
        $urlEncoded = str_replace(['%3A', ','], [':', '%2C'], $urlEncoded);

        // ex: https://www.e.leclerc/api/rest/elpev-api/list?filters=%7B%22type%22:%7B%22value%22:%2201%22%7D,%22storePanonceauCode%22:%7B%22value%22:%221375%22%7D%7D&page=1&size=20
        return self::SEARCH_URL_BASE . $urlEncoded . self::SEARCH_URL_PARAMS;
    }

    private function addBrochureIfNotExist(object $item): void
    {
        $brochureId = $this->getBrochureId($item);
        if ($brochureId) {
            if (isset($this->brochuresToImport[$brochureId])) {
                // add the store number to the list of stores for the current brochure
                $this->brochuresToImport[$brochureId]['stores'][] = $item->storePanonceauCode;
            }
            else {
                $this->addBrochureForImport($brochureId, $item);
            }
        }
    }

    private function getBrochureId(object $item): string
    {
        $pattern = '#catalog/([^/]+)/#';
        if (!preg_match($pattern, $item->flashLink, $idMatch)) {
            return '';
        }

        return $idMatch[1];
    }

    private function addBrochureForImport(string $brochureId, object $item): void
    {
        $this->brochuresToImport[$brochureId] = [
            'id' => $brochureId,
            'url' => $this->getBrochureUrl($brochureId, $item->storePanonceauCode),
            'start' => date(self::DATE_FORMAT, strtotime($item->participationStartDate)),
            'end' => date(self::DATE_FORMAT, strtotime($item->participationEndDate)),
            'visibleFrom' => date(self::DATE_FORMAT, strtotime($item->displayStartDate)),
            'stores' => [
                $item->storePanonceauCode
            ]
        ];
    }

    private function getBrochureUrl(string $brochureId, string $storeId): string
    {
        $url = self::DOWNLOAD_URL_BASE . $brochureId . '/' . $storeId . '/pdf';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_REFERER, self::DOWNLOAD_URL_ORIGIN);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Origin: ' . self::DOWNLOAD_URL_ORIGIN,
            'X-Requested-With: XMLHttpRequest'
        ));
        $result = curl_exec($ch);
        curl_close($ch);

        $filePath = $this->localPath . 'ELeclerc-' . $brochureId . '.pdf';

        $fh = fopen($filePath, 'w+');
        fwrite($fh, $result);
        fclose($fh);

        return $this->sHttp->generatePublicHttpUrl($filePath);
    }

    private function generateBrochure(array $importData): Marktjagd_Entity_Api_Brochure
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('ELeclercFr')
                ->setUrl($importData['url'])
                ->setStart($importData['start'])
                ->setEnd($importData['end'])
                ->setVisibleStart($importData['visibleFrom'])
                ->setStoreNumber(implode(',', $importData['stores']))
                ->setVariety('leaflet')
                ->setBrochureNumber($importData['id']);

        return $eBrochure;
    }
}
