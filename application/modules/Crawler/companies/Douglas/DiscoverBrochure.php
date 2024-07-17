<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Brochure Crawler für Douglas (ID: 326)
 */
class Crawler_Company_Douglas_DiscoverBrochure extends Crawler_Generic_Company
{
    private const TRACKING_BUG_URL = 'https://m.exactag.com/ai.aspx?extCa=717&extTcm=de.06r.off|.discover.top.000001&cb=%%CACHEBUSTER%%';
    private const COVER_ASSIGNMENTS_PATH = './Discover/cover_page_assignments.xlsx';
    private const CATEGORY_TO_PAGE_MAP = [
        'Parfum' => 0,
        'Make-up' => 1,
        'Gesicht' => 2,
        'Körper' => 3,
        'Haare' => 4,
        'Apotheke¹ & Gesundheit' => 5
    ];

    private Marktjagd_Service_Transfer_FtpMarktjagd $ftp;
    private string $localPath;
    private int $companyId;

    public function __construct()
    {
        parent::__construct();

        $this->ftp = new Marktjagd_Service_Transfer_FtpMarktjagd();
    }

    public function crawl($companyId)
    {
        $googleSpreadsheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();

        $this->companyId = $companyId;
        $this->localPath = $this->ftp->connect($companyId, TRUE);

        $campaignData = $googleSpreadsheet->getFormattedInfos('1fDgXOh3RjKwBa0ojgHORzvmvPAl4MStJjwd5LPpwPlA', 'A1', 'F', 'douglasGer')[0];

        $coverPage = $this->getCoverPage($campaignData['coverPage']);
        $coverWithClickout = $this->setClickoutToCover($coverPage);

        $layout = $this->getLayout($campaignData);

        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Douglas: ' . $campaignData['title'])
            ->setBrochureNumber($campaignData['brochureNumber'])
            ->setUrl($coverWithClickout)
            ->setVariety('leaflet')
            ->setStart($campaignData['validityStart'])
            ->setEnd($campaignData['validityEnd'])
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($layout)
            ->setTrackingBug(self::TRACKING_BUG_URL);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

    private function getCoverPage(string $initialCover): string
    {
        $fileToUse = $initialCover;

        // need to figure out a way to make it configurable easy
        $coverAssignmentsFile = $this->ftp->downloadFtpToDir(self::COVER_ASSIGNMENTS_PATH, $this->localPath);

        $coversList = [
            '28.05.2023' => 'SD-73196_Offerista_1700x2405_Model_04.pdf',
            '04.06.2023' => '1700x2405-Offerista-1.pdf',
            '06.06.2023' => 'SD-73196_Offerista_1700x2405_Products_01.pdf',
            '11.06.2023' => 'SD-73196_Offerista_1700x2405_Model_03.pdf',
            '15.06.2023' => 'SD-73196_Offerista_1700x2405_Products_02.pdf',
            '20.06.2023' => 'SD-73196_Offerista_1700x2405_Model_05.pdf',
            '25.06.2023' => '1700x2405-Offerista-1.pdf',
            '27.06.2023' => 'SD-73196_Offerista_1700x2405_Model_01.pdf',
        ];

        foreach ($coversList as $date => $fileName) {
            if (strtotime('now') >= strtotime($date)) {
                $fileToUse = $fileName;
            } else {
                break;
            }
        }

        foreach ($this->ftp->listFiles('./Discover') as $coverPdf) {
            if (preg_match('#' . $fileToUse . '#', $coverPdf)) {
                $localBrochureFile = $this->ftp->downloadFtpToDir($coverPdf, $this->localPath);
                break;
            }
        }

        $this->ftp->close();

        if (!isset($localBrochureFile)) {
            throw new Exception('No cover page found for ');
        }

        return $localBrochureFile;
    }

    private function setClickoutToCover(string $coverPage): string
    {
        $pdfService = new Marktjagd_Service_Output_Pdf();

        $clickoutUrl = 'http://m.exactag.com/cl.aspx?extCa=717&extTcm=de.06r.off|.discover.top.000001&url=https%3A%2F%2Fwww.douglas.de%2Fde%2F%3Ftrac%3Dde.06r.off..discover.top.000001%26et_sea%3D1';
        if (preg_match('#.*/.*(Products|Model)#', $coverPage)) {
            $clickoutUrl = 'https://m.exactag.com/cl.aspx?extCa=717&extTcm=de.06r.off|.discover.top.000001&url=https%3A%2F%2Fwww.douglas.de%2Fde%2F%3Ftrac%3Dde.06r.off..discover.top.000001%26et_sea%3D1';
        }

        $aPdfInfos = $pdfService->getAnnotationInfos($coverPage);

        $clickouts[] = [
            'page' => 0,
            'height' => $aPdfInfos[0]->height,
            'width' => $aPdfInfos[0]->width,
            'startX' => $aPdfInfos[0]->width / 3,
            'endX' => $aPdfInfos[0]->width / 3 * 2,
            'startY' => $aPdfInfos[0]->height / 3,
            'endY' => $aPdfInfos[0]->height / 3 * 2,
            'link' => $clickoutUrl
        ];

        $coordFileName = $this->localPath . 'coordinates_' . $this->companyId . '.json';
        $fh = fopen($coordFileName, 'w+');
        fwrite($fh, json_encode($clickouts));
        fclose($fh);

        return $pdfService->setAnnotations($coverPage, $coordFileName);
    }

    private function getLayout(array $campaignData): string
    {
        $api = new Marktjagd_Service_Input_MarktjagdApi();
        $articlesFeed = Crawler_Company_Douglas_DiscoverArticle::getArticlesFeed($this->companyId);

        $aApiData = $api->getActiveArticleCollection($this->companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $productPages = [];
        foreach ($articlesFeed as $articleData) {
            if (is_null($articleData['category']) || is_null($aArticleIds[$campaignData['brochureNumber'] . '_' . $articleData['article_number']])) {
                continue;
            }

            elseif (!isset(self::CATEGORY_TO_PAGE_MAP[$articleData['category']])) {
                throw new Exception('category "' . $articleData['category'] . '" not found in map for company ' . $this->companyId);
            }

            $page = self::CATEGORY_TO_PAGE_MAP[$articleData['category']];
            if ('disapo.de' === $articleData['vendor']) {
                $page = self::CATEGORY_TO_PAGE_MAP['Apotheke¹ & Gesundheit'];
            }

            $product = [
                'product_id' => $aArticleIds[$campaignData['brochureNumber'] . '_' . $articleData['article_number']],
                'priority' => rand(1, 3),
            ];
            $productPages[$page]['page_metaphor'] = $articleData['category'];
            $productPages[$page]['products'][] = $product;
        }

        ksort($productPages);

        $response = Blender::blendApi($this->companyId, $productPages, $campaignData['brochureNumber']);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out for company ' . $this->companyId);
        }

        return $response['body'];
    }

}