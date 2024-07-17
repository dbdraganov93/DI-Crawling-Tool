<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * Discover Crawler für EDEKA SW (ID: 2) - Stage-ID: 78478
 * * This is also Edeka Kopernikus project
 */
class Crawler_Company_EdekaSW_DiscoverBrochure extends Crawler_Generic_Company
{
    protected $_mafoFile;
    public function crawl($companyId)
    {
        $week = 'next';
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $aStores = [
            1 => [
                'url' => 'https://edeka-wochenangebote.de/?utm_source=OU&utm_medium=cpc&utm_campaign=K12',
                'stores' => ['8000834','246196','19165','8000182','8000039','10002517','10001891','8000909','246210','5549182','8002841']],
            2 => [
                'url' => 'https://edeka-wochenangebote.de/?utm_source=OU&utm_medium=cpc&utm_campaign=K13',
                'stores' => ['8002321','6281104','349382','5541504','10002020','90939']],
            3 => [
                'url' => 'https://edeka-wochenangebote.de/?utm_source=OU&utm_medium=cpc&utm_campaign=K15',
                'stores' => ['109209']],
            4 => [
                'url' => 'https://edeka-wochenangebote.de/?utm_source=OU&utm_medium=cpc&utm_campaign=K7',
                'stores' => ['8002069', '50','5008','8001456','191742','8003006','10094','10000939','83718','17134','349513','21726','6061386','20645','192547','8002881','8000297','60037','8002880','8001706','5191','93287','10003026','3840892','597205','4538078','10000940','10229','8000511','109180','8000498','1234568','84881','2712576','8003009','10001606','243892','10003045','8000008','10003042']],
        ];

        # the "Highlights" are a special category at the top of the Discover (up to 10 product_ids)
        $highlightsGSheetId = '1nDVlN7ZNp_FN6BiCeLhy__VOIjEU1JWk0jX3Ahr6B7s';
        $sGSheet = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $aData = $sGSheet->getFormattedInfos($highlightsGSheetId, 'A1', 'A', 'Kopernikus_aktuelle_KW');
        $highlightIds = [];
        foreach ($aData as $singleRow) {
            $highlightIds[] = $singleRow['product_number'];
        }


        # get the cover page from the Edeka (ID 2) FTP folder
        $firstPage = '';
        $localPath = $sFtp->connect(2, TRUE);
        foreach ($sFtp->listFiles() as $singleFile) {

            if (preg_match('#KW' . date('W', strtotime($week . ' tuesday')) . '#', $singleFile)) {
                $firstPage = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        if (!strlen($firstPage)) {
            throw new Exception($companyId . ': unable to find first page for week ' . date('W', strtotime($week . ' week')));
        }

        # get the Mafo-page from the Edeka-SW FTP folder
        $sFtp->connect(71668, TRUE);
        $this->_mafoFile = $sFtp->downloadFtpToDir('Mafo_EDEKA Suedwest.pdf', $localPath);

        # get the alternative pdf from the Edeka-SW FTP folder (if discover is not supported, we show this)
        $aBrochureToAssign = [];
        foreach ($sFtp->listFiles() as $singleFolder) {
            if (!preg_match('#KW' . date('W', strtotime($week . ' tuesday')) . '#', $singleFolder)) {
                continue;
            }
            foreach ($sFtp->listFiles($singleFolder) as $singleFile) {
                $pattern = '#\/KW' . date('W', strtotime($week . ' tuesday')) . '_(\d{6})_(\d{6})_SUEDWEST_MG_318_ED\.pdf#';
                if (!preg_match($pattern, $singleFile, $validityMatch)) {
                    continue;
                }
                $aBrochureToAssign = [
                    'filePath' => $sFtp->downloadFtpToDir($singleFile, $localPath),
                    'visibleStart' => preg_replace('#(\d{2})(\d{2})(\d{2})#', '$1.$2.20$3', $validityMatch[1]),
                    'validEnd' => preg_replace('#(\d{2})(\d{2})(\d{2})#', '$1.$2.20$3', $validityMatch[2])
                ];
            }

            $sFtp->close();

            # get the list of articles
            $token = $this->_getApiAccessToken();
            $articles = $this->_getArticlePage($token['access_token'], 1000);

            $cBrochures = new Marktjagd_Collection_Api_Brochure();
            # build one discover per entry in the stores-array
            foreach ($aStores as $brochureNumber => $aInfos) {
                $brochureNumberLong = 'DC_Kopernikus' . $brochureNumber . '_KW' . date('W', strtotime($aBrochureToAssign['validEnd'])) . '_' . date('y', strtotime($aBrochureToAssign['visibleStart']));

                # build the discover product array
                $this->_logger->info("preparing blender request");
                $categories = [];
                $categoryOrder = [];
                foreach ($articles->offers as $article) {

                    $productId = $sApi->findArticleByArticleNumber($companyId, $article->id . '_K' . $brochureNumber)['id'];
                    if(!$productId) {
                        $this->_logger->warn("Error: Cannot query ". $article->id . '_K' . $brochureNumber . " from our API");
                        continue;
                    }

                    # if it's in the highlight list, we change the category
                    if(in_array($article->id,$highlightIds)) {
                        $article->criteria[0]->name = 'Unsere Highlights';
                        $article->criteria[0]->id = 0;
                    }

                    # start a new page per category name
                    if (!array_key_exists($article->criteria[0]->name, $categories)) {
                        $categories[$article->criteria[0]->name] = [];
                    }

                    # the category->id is used as page number
                    if (!array_key_exists($article->criteria[0]->name, $categoryOrder)) {
                        $categoryOrder[$article->criteria[0]->name] = $article->criteria[0]->id;
                    }

                    # we add up to 10 products per page
                    if (count($categories[$article->criteria[0]->name]) < 10) {
                        $categories[$article->criteria[0]->name][] = [
                            'product_id' => $productId,
                            'priority' => rand(1, 3)
                        ];
                    }
                }

                $pageIndex = array_key_exists('Unsere Highlights', $categoryOrder)? 0 : -1;

                $discover = [];
                foreach ($categories as $categoryTitle => $products) {
                    $discover[$categoryOrder[$categoryTitle] + $pageIndex] = [
                        'page_metaphore' => $categoryTitle,
                        'products' => $products
                    ];
                }
                ksort($discover);

//                $this->_logger->info("requesting discover layout");
//                $response = Blender::blendApi($companyId, $discover);
//
//                if ($response['http_code'] != 200) {
//                    $this->_logger->err($response['error_message']);
//                    $strLayout = null;
//                } else {
//                    $strLayout = $response['body'];
//                }

                # add the clickout link to the first page
                $fileName = APPLICATION_PATH . '/../public/files/tmp/coords_' . $brochureNumber . '.json';
                $fh = fopen($fileName, 'w+');
                fwrite($fh,
                    json_encode(
                        [
                            [
                                'page' => 0,
                                'startX' => 240.605,
                                'startY' => 630.83527,
                                'endX' => 290.102,
                                'endY' => 654.1392,
                                'link' => $aInfos['url']
                            ]
                        ]
                    )
                );
                fclose($fh);
                $firstPageLinked = $sPdf->setAnnotations($firstPage, $fileName);

                # merge the first page with the alternative pdf
                $filePath = $sPdf->merge([$firstPageLinked, $aBrochureToAssign['filePath']], $localPath);
                # and insert the mafo file
                #$filePath = $this->insertMafo($filePath); # not wanted this time

                $eBrochure = new Marktjagd_Entity_Api_Brochure();

                $eBrochure->setUrl($filePath)
                    ->setTitle('Wochenangebote KW' . date('W', strtotime($aBrochureToAssign['validEnd'])))
                    ->setBrochureNumber($brochureNumberLong)
                    ->setEnd($aBrochureToAssign['validEnd'])
                    ->setVisibleStart($aBrochureToAssign['visibleStart'])
                    ->setStart(date('d.m.Y', strtotime($eBrochure->getVisibleStart() . '+1 day')))
                    ->setVariety('leaflet')
                    ->setStoreNumber(implode(",", $aInfos['stores']))
                    #->setLayout($strLayout)
                ;

                $cBrochures->addElement($eBrochure);
            }
        }
        return $this->getResponse($cBrochures);
    }

    /**
     * Queries Edekas OAuth 2 API to get an access token for their article api
     * @return array
     * @throws Exception
     */
    private function _getApiAccessToken(): array
    {
        $username = 'offerista-digital-handzettel';
        $password = '#IeeNuzUt0sDJF8RxhT0e+';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://login.api.edeka/v1/auth-service/token',
            CURLOPT_POST => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($username . ':' . $password)
            ]
        ]);

        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch)['http_code'];
        if ($http_code != 200) {
            throw new Exception('ERROR getting OAuth Access Code - HTTP_CODE: ' . $http_code . '---' . implode('\n', $response));
        }

        curl_close($ch);
        $jsonBody = json_decode($response);

        return array(
            'access_token' => $jsonBody->access_token,
            'expires_in' => $jsonBody->expires_in
        );
    }

    /**
     * Queries a given page from the EDEKA article api for market_id 41
     * @param $access_token
     * @return mixed
     * @throws Exception
     */
    private
    function _getArticlePage($access_token, $pageSize = 500)
    {
        $endpoint = 'https://b2c-gw.api.edeka/v1/offers/mobile';
        $params = array(
            'marketId' => 41,
            'size' => $pageSize
        );

        $url = $endpoint . '?' . http_build_query($params);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token
            ]
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch)['http_code'];
        if ($http_code != 200) {
            throw new Exception('ERROR getting products - HTTP_CODE: ' . $http_code . '---' . implode('\n', $response));
        }

        curl_close($ch);
        return json_decode($response);
    }

    /**
     * @param $brochureFile
     * @return mixed
     * @throws Zend_Exception
     */
    private function insertMafo($brochureFile)
    {
        $sPdf = new Marktjagd_Service_Output_Pdf();
        if ($sPdf->getPageCount($brochureFile) <= 3) {
            $brochureFile = $sPdf->merge([$brochureFile, $this->_mafoFile], dirname($brochureFile) . '/');
        } else {
            $brochureFile = $sPdf->insert($brochureFile, $this->_mafoFile, 3);
        }
        return $brochureFile;
    }
}
