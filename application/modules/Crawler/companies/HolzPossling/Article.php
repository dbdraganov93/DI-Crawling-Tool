<?php

/**
 * Article crawler for Holz Possling (ID: 71464)
 */

class Crawler_Company_HolzPossling_Article extends Crawler_Generic_Company
{
    protected int $_companyId;

    public function crawl($companyId)
    {
        ini_set('memory_limit', '2048M');
        $this->_companyId = $companyId;
        $sPage = new Marktjagd_Service_Input_Page();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();

        $localPath = $sFtp->connect($companyId, TRUE);
        $aInfos = $sGSRead->getCustomerData('holzPosslingGer');

        foreach ($sFtp->listFiles('./discover') as $singleRemoteFile) {
            if (preg_match('#' . $aInfos['articleFile'] . '#', $singleRemoteFile)) {
                $localArticleFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        $aSpreadsheets = $sPss->readFile($localArticleFile)->getElements();
        foreach ($aSpreadsheets as $singleSpreadsheet) {
            if (preg_match('#Datenfeed#', $singleSpreadsheet->getTitle())) {
                $aData = $singleSpreadsheet->getData();
                break;
            }
        }

        $aHeader = [];
        $aArticles = [];
        foreach ($aData as $singleRow) {
            if (!$singleRow[2]) {
                continue;
            }
            if (!$aHeader) {
                $aHeader = $singleRow;
                continue;
            }
            $singleArticle = array_combine($aHeader, $singleRow);

            $aTitleText = preg_split('#\n#', $singleRow[5]);
            $singleArticle['title'] = $aTitleText[0];
            if (count($aTitleText) > 1) {
                for ($i = 1; $i < count($aTitleText); $i++) {
                    $aText[] = $aTitleText[$i];
                }
                $singleArticle['Kurztext'] = implode("\n", $aText);
            }
            $aArticles[] = $singleArticle;
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aArticles as $singleArticle) {
            if (empty($singleArticle['ArtNr'])) {
                continue;
            }
            $this->_logger->info('getting article ' . $singleArticle['ArtNr']);
            $getArticleNumber = $this->mainCurlInfo($singleArticle['ArtNr']);

            $getArticleTitle = $this->getArticleTitle($singleArticle, $sPage);

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setTitle($getArticleTitle)
                ->setText($singleArticle['Kurztext'])
                ->setArticleNumber($singleArticle['ArtNr'])
                ->setPrice(preg_replace('#\s*€#', '', trim($singleArticle['Werbepreis'])))
                ->setImage('https://www.possling.de' . $getArticleNumber->haupt_artikelbild)
                ->setUrl($singleArticle["Produkt"])
                ->setStart($aInfos['validStart'])
                ->setEnd($aInfos['validEnd'])
                ->setVisibleStart($eArticle->getStart());

            if (is_float($singleArticle['Grundpreis'])) {
                $additionalProperties = [
                    "unitPrice" => [
                        "value" => round((float)trim(preg_replace(['#([^/]+)\s*/[^/]+#', '#\,#', '#[^\d\.]#'], ['$1', '.', ''], $singleArticle['Grundpreis'])), 2),
                        "unit" => preg_replace('#\.#', '', $singleArticle["Einheit"])
                    ]
                ];

                $eArticle->setAdditionalProperties(json_encode($additionalProperties));
            }

            $cArticles->addElement($eArticle, TRUE, 'complex', FALSE);

        }

        return $this->getResponse($cArticles, $companyId);
    }

    private function mainCurlInfo(string $articleNumber)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://www.possling.de/scripts/preisliste/artikeldetails_neu.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => 'artnr=' . $articleNumber,
            CURLOPT_HTTPHEADER => array(
                'Connection: keep-alive',
                'Pragma: no-cache',
                'Cache-Control: no-cache',
                'sec-ch-ua: " Not;A Brand";v="99", "Google Chrome";v="91", "Chromium";v="91"',
                'Accept: application/json, text/javascript, */*; q=0.01',
                'X-Requested-With: XMLHttpRequest',
                'sec-ch-ua-mobile: ?0',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                'Origin: https://www.possling.de',
                'Sec-Fetch-Site: same-origin',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Dest: empty',
                'Accept-Language: de,en-US;q=0.9,en;q=0.8',
                'Cookie: cookie_erlaubnis=ja; PHPSESSID=1db3eae66407c5370826ce6cddff4dff'
            ),
        ));

        $response = json_decode(curl_exec($curl));
        curl_close($curl);
        return $response;
    }

    private function getArticleTitle($singleArticle, $sPage): ?string
    {
        $pattern = '#(katalog/(\d+)/|searchterm=(\d+))#';
        if (!preg_match($pattern, $singleArticle['Produkt'], $articleNumberMatch)) {
            $this->_logger->err($this->_companyId . ': ' . $singleArticle['Produkt']);
            return null;
        }
        $articleUrl = 'https://www.possling.de/preisliste/Suchanfrage/katalog/' . $articleNumberMatch[count($articleNumberMatch) - 1] . '/galerie/artikel.php';
        $this->_logger->info($this->_companyId . ': opening ' . $articleUrl);

        $sPage->open($articleUrl);
        $page = $sPage->getPage()->getResponseBody();

        $pattern = '#<title[^>]*>[^-]+-\s*([^<]+?)</title>#';
        if (!preg_match($pattern, $page, $titleMatch)) {
            $this->_logger->err($this->_companyId . ': ' . $singleArticle['Produkt']);
            return null;
        }

        return $titleMatch[1];
    }
}
