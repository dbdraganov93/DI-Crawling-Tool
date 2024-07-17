<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Artikelcrawler für Forstinger AT (ID: 73469)
 */
class Crawler_Company_ForstingerWgw_Discover extends Crawler_Generic_Company
{
    private const MAX_DISCOVER_PRODUCTS = 150;
    private const ARTICLE_FILE = 'Dezember.xls';
    private const BROCHURE_NUMBER = 'Presiknallerwoche_1222';
    private const BROCHURE_PDF_FILE = 'Preisknaller-Wochen_Dezember_gueltig von 19-31_12_2022_V1.pdf';
    private const START_DATE = '19.12.2022';
    private const END_DATE = '31.12.2022';
    private const IMAGES_FOLDER = '';



    public function crawl($companyId)
    {

        $discoverPath = $companyId . '/Dezember';

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localFolderPath = $sFtp->connect($discoverPath, true);


        // change variable const in case they provide extra images
        $sFtp->changedir(self::IMAGES_FOLDER);

        $localArticleFile = $sFtp->downloadFtpToDir('./' . self::ARTICLE_FILE, $localFolderPath);
        $mainBrochure = $sFtp->downloadFtpToDir('./' . self::BROCHURE_PDF_FILE, $localFolderPath);

        $sFtp->close();

        $aData = $sPss->readFile($localArticleFile, true)->getElement(0)->getData();

        $articlesCatsAndNumber = [];
        foreach ($aData as $singleRow) {

            if (empty($singleRow['Kategorie'])) {
                $this->_logger->info('Skipping product: ' . $singleRow['URL']);
                continue;
            }

            $articlesCatsAndNumber[$singleRow['Seite']][$singleRow['Kategorie']][] = $singleRow['Artikelnummer'];
        }

        $aApiData = $sApi->getActiveArticleCollection($companyId);

        $aArticleIdsAndCats = [];
        /** @var Marktjagd_Entity_Api_Article $eApiData */
        foreach ($aApiData->getElements() as $eApiData) {
            if (!preg_match('#DISCOVER_#', $eApiData->getArticleNumber())) {
                continue;
            }

            if (count($aArticleIdsAndCats) >= self::MAX_DISCOVER_PRODUCTS) {
                $this->_logger->crit('Oh noes! We have more than 150 products in the list! Only 150 products will be imported -> Contact the PDM');
                break;
            }

            $aArticleIdsAndCats[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        ksort($aArticleIdsAndCats);

        $finalProductsArrayAndCats = [];
        foreach ($articlesCatsAndNumber as $page) {
            foreach ($page as $category => $articleNumbers) {
                foreach ($articleNumbers as $articleNumber) {
                    if ($articleNumber == 'Windschutzscheibentausch') {
                        $finalProductsArrayAndCats[trim((string)$category)][] = $aArticleIdsAndCats['DISCOVER_Windschutz'];
                        continue;
                    }
                    if (empty($aArticleIdsAndCats['DISCOVER_' . $articleNumber])) {
                        continue;
                    }

                    $finalProductsArrayAndCats[trim((string)$category)][] = $aArticleIdsAndCats['DISCOVER_' . $articleNumber];
                }
            }
        }

        $aNewGen = [];
        $index = 1;

        foreach ($finalProductsArrayAndCats as $cat => $products) {
            $aNewGen[$index]['page_metaphor'] = $cat;

            foreach ($products as $product) {
                $aNewGen[$index]['products'][] = [
                    'product_id' => $product,
                    'priority' => (string)rand(1, 3),
                ];
            }
            $index++;
        }

        ksort($aNewGen);

        $response = Blender::blendApi($companyId, $aNewGen, self::BROCHURE_NUMBER);

        if ($response['http_code'] != 200) {
            $this->_logger->err($response['error_message']);
            throw new Exception('blender api did not work out');
        }

        $strLayout = $response['body'];
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setTitle('Forstinger')
            ->setBrochureNumber(self::BROCHURE_NUMBER)
            ->setUrl($mainBrochure)
            ->setVariety('leaflet')
            ->setStart(self::START_DATE)
            ->setEnd(self::END_DATE)
            ->setVisibleStart($eBrochure->getStart())
            ->setLayout($strLayout);

        $cBrochures->addElement($eBrochure);

        return $this->getResponse($cBrochures, $companyId);
    }

}

