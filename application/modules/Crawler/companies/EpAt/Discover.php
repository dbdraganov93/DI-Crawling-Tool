<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für EP: (AT) (ID: 72750)
 */
class Crawler_Company_EpAt_Discover extends Crawler_Generic_Company
{
    private const PAGES_MAP = [
        'TV & Audio' => 1,
        'Telefon & Navi' => 2,
        'Computer & Büro' => 3,
        'Haushalt & Küche' => 4,
        'Körperpflege & Gesundheit' => 5,
    ];
    private const DATE_FORMAT = 'd.m.Y';

    private int $companyId;
    private bool $centralEpStoreFound = false;
    private array $articleList = [];

    private Crawler_Company_EpAt_DiscoverHelpers $helperMethods;

    public function __construct()
    {
        parent::__construct();

        $this->helperMethods = new Crawler_Company_EpAt_DiscoverHelpers();
    }

    public function crawl($companyId)
    {
        ini_set('memory_limit', '2G');

        $this->companyId = $companyId;

        $this->articleList = $this->getActiveArticleList();
        if (empty($this->articleList)) {
            return $this->getGenericResponse();
        }

        $zipFiles = $this->helperMethods->downloadZipsFromClientFTP($companyId);

        $brochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($zipFiles as $zip) {
            $extractedFiles = $this->extractArticlesAndBrochureFiles($zip);

            foreach ($extractedFiles['articles'] as $storeNumber => $articleFile) {
                $this->centralEpStoreFound = $this->centralEpStoreFound || $this->helperMethods->isCentralEpStore($storeNumber);

                $brochureData = $this->getBrochureData($articleFile, $extractedFiles['brochure'], $storeNumber, $zip);
                sleep(10);
                $brochure = $this->createBrochure($brochureData);
                $brochures->addElement($brochure);
            }

            # do the last step once again for the central EP shop if it has no dedicated Discover
            if (!$this->centralEpStoreFound) {
                $articleFile = end($extractedFiles['articles']);
                $brochureData = $this->getBrochureData($articleFile, $extractedFiles['brochure'], $this->helperMethods->getCentralEPStore(), $zip);
                $brochure = $this->createBrochure($brochureData);
                $brochures->addElement($brochure);
            }
        }

        if (count($brochures->getElements()) == 0) {
            return $this->getGenericResponse();
        }

        return $this->getResponse($brochures, $companyId);
    }

    private function getActiveArticleList(): array
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();

        $articles = $api->getActiveArticleCollection($this->companyId)->getElements();

        $articleList = [];
        foreach ($articles as $article) {
            $articleList[$article->getArticleNumber()] = $article->getArticleId();
        }

        return $articleList;
    }

    private function extractArticlesAndBrochureFiles(string $zipFile): array
    {
        $extractPath = $this->helperMethods->unzipFile($zipFile, $this->companyId);

        $directory = new RecursiveDirectoryIterator($extractPath, FilesystemIterator::SKIP_DOTS);
        $recursiveIterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);

        $articleFiles = [];
        $brochure = '';
        foreach ($recursiveIterator as $file) {
            if (0 !== strpos($file->getBasename(),'.')) {
                if (preg_match('#\.csv$#', $file->getBasename())) {
                    $articleFiles[$file->getBasename('.csv')] = $file->getPathname();
                    continue;
                }

                if (preg_match('#\.pdf$#', $file->getBasename())) {
                    $brochure = $file->getPathname();
                }
            }
        }

        return [
            'articles' => $articleFiles,
            'brochure' => $brochure
        ];
    }

    private function getBrochureData(string $articlesFile, string $brochureUrl, string $storeNumber, string $zipFile = ''): array
    {
        $spreadsheetService = new Marktjagd_Service_Input_PhpSpreadsheet();

        $url = $this->copyBrochureFile($brochureUrl, $storeNumber);
        $number = '';
        $start = '';
        $end = '';
        $productPages = [];
        $store = $storeNumber;
        $national = 0;
        if ($this->helperMethods->isCentralEPStore($storeNumber)) {
            $store = NULL;
            $national = 1;
        }

        $articlesData = $spreadsheetService->readFile($articlesFile, TRUE, ';')->getElement(0)->getData();
        foreach ($articlesData as $articleData) {
            if (strtotime($articleData['end']) < time() || empty($articleData['ID']) || empty($articleData['Catgegory'])) {
                continue;
            }

            $articleNumber = basename($zipFile, '.zip') . '_' . $storeNumber . '_' . $articleData['ID'];
            if (!$this->articleList[$articleNumber]) {
                $this->_logger->err('The article ' . $articleNumber . ' doesn\'t exist in the BT');
                continue;
            }

            $page = self::PAGES_MAP[$articleData['Catgegory']];
            if(empty($page)) {
                $this->_logger->err('The file: ' . $storeNumber . ' has a missing Category ' . $articleData['Catgegory']);
                continue;
            }

            $number = $articleData['Brochure ID'] ? substr($storeNumber . '_' . $articleData['Brochure ID'], 0, 32) : $number;
            $start = $articleData['start'] ?? $start;
            $end = $articleData['end'] ?? $end;

            $productPages[$page]['products'][] = [
                'priority' => $articleData['Layout Priority'],
                'product_id' => $this->articleList[$articleNumber]
            ];
            $productPages[$page]['page_metaphor'] = $articleData['Catgegory'];
        }

        ksort($productPages);

        $this->_logger->info('Blending ' . $storeNumber);
        $response = Blender::blendApi($this->companyId, $productPages);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            $layout = null;
        } else {
            $layout = $response['body'];
        }

        return [
            'url' => $url,
            'number' => $number,
            'start' => date(self::DATE_FORMAT, strtotime($start)),
            'end' => date(self::DATE_FORMAT, strtotime($end)),
            'layout' => $layout,
            'store' => $store,
            'national' => $national
        ];
    }

    private function createBrochure(array $brochureData): Marktjagd_Entity_Api_Brochure
    {
        $brochure = new Marktjagd_Entity_Api_Brochure();

        return $brochure->setTitle('EP: Aktuelle Angebote')
            ->setBrochureNumber($brochureData['number'])
            ->setUrl($brochureData['url'])
            ->setVariety('leaflet')
            ->setStart($brochureData['start'])
            ->setEnd($brochureData['end'])
            ->setVisibleStart($brochureData['start'])
            ->setStoreNumber($brochureData['store'])
            ->setLayout($brochureData['layout'])
            ->setNational($brochureData['national']);
    }

    private function copyBrochureFile(string $brochurePath, string $storeNumber): string
    {
        $newBrochurePath = preg_replace('#.pdf#', '_' . $storeNumber . '.pdf', $brochurePath);
        if (!copy($brochurePath, $newBrochurePath)) {
            throw new Exception('Company ID: ' . $this->companyId . ': Could not copy brochure file ' . $brochurePath . ' to ' . $newBrochurePath);
        }

        return $newBrochurePath;
    }

    private function getGenericResponse(string $message = 'no valid articles online'): Crawler_Generic_Response
    {
        $this->_logger->info($message);
        $this->_response->setIsImport(false);
        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT);
        return $this->_response;
    }
}
