<?php

/*
 * Brochure Crawler für Pflanzen Kölle (ID: 69974)
 */

class Crawler_Company_PflanzenKoelle_DynArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $sFtp->connect($companyId);
        $localFolder = $sFtp->generateLocalDownloadFolder($companyId);
        $localAssignmentFile = '';

        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#artikel.csv?#i', $singleFile)) {
                $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localFolder);
                if (strlen($localAssignmentFile)) {
                    $this->_logger->info($companyId . ': ' . $singleFile . ' downloaded.');
                }
            }


        }
        $csvData = $sExcel->readFile($localAssignmentFile, true, ';')->getElement(0)->getData();

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($csvData as $product) {
            if (strlen($product['Artikel']) < 5) {
                continue;
            }
            $page = $this->getRemotePageWithCookies($product['Artikel']);
            preg_match('#dataLayer = (.*?)\n#i', $page, $data);
            $dataJson = substr($data[1], 1, -2);
            $dataJson = json_decode($dataJson);
            $descriptionArray = preg_split('#,#', $dataJson->productName);
            $textTitleArr = $this->getTextAndTitle($descriptionArray);

            preg_match('#og:image" ([^>].*)#i', $page, $url);
            $newUrl = preg_split('#"#', $url[1]);
            preg_match('#itemprop="price" content=([^>].*)#i', $page, $price);
            $price = preg_split('#"#', $price[1]);

            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber($dataJson->productSku)
                ->setTitle($textTitleArr['title'])
                ->setPrice($price[1])
                ->setText($textTitleArr['text'])
                ->setUrl($product['Artikel'])
                ->setImage($newUrl[1]);
            $cArticles->addElement($eArticle);
            sleep(3);
        }


        return $this->getResponse($cArticles, $companyId);
    }

    //dataLayer = [{"pageTitle":"Olivenbaum, Olea europaea 'Stamm' online kaufen | Pflanzen-K\u00f6lle | Pflanzen-K\u00f6lle Gartencenter GmbH & Co. KG","pageCategory":"Detail","pageSubCategory":"","pageCategoryID":904,"productCategoryPath":"Pflanzen\/Beet- & Balkonpflanzen","pageSubCategoryID":"","pageCountryCode":"de_DE","pageLanguageCode":"de","pageVersion":1,"pageTestVariation":"1","pageValue":1,"pageAttributes":"1","productID":51741,"productStyleID":"","productEAN":"4066169010348","productName":"K\u00f6lle Olivenbaum, Olea europaea 'Stamm', Topf 19 cm \u00d8, Gesamth\u00f6he ca. 70 - 90 cm","productPrice":"18.68","productCategory":"Beet- & Balkonpflanzen","productCurrency":"EUR","productColor":"","productRealColor":"","visitorId":"","visitorLoginState":"Logged Out","visitorType":"NOT LOGGED IN","visitorDemographicInfo":"","visitorSocialConnections":"","visitorLifetimeValue":0,"visitorExistingCustomer":"No","productSku":"0680200041"}];

    public function getRemotePageWithCookies($url, $dataName = '')
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        if (isset($parsedUrl['path'])) {
            $path = $parsedUrl['path'];
        } else {
            $path = '/';
        }

        if (strlen($dataName)) {
            $fileName = $dataName;
        } else {
            $aUrl = explode('/', $path);
            $fileName = preg_replace('#[\.]([^\.]+?\.[^\.]+)#', '$1', end($aUrl));
        }
        if (!strlen($fileName)) {
            $fileName = 'tmp_file';
        }

        if (isset($parsedUrl['query'])) {
            $path .= '?' . $parsedUrl['query'];
        }

        if (isset($parsedUrl['port'])) {
            $port = $parsedUrl['port'];
        } else {
            $port = '80';
        }

        $newUrl = $parsedUrl['scheme'] . '://' . $host . $path;

        // Difference from $sHttp->_curlOneFile() is that here we need to create cookies
        $cookie_path = 'cookie.txt';
        if (defined('COOKIE_PATH_FOR_CURL') && !empty(COOKIE_PATH_FOR_CURL)) {
            $cookie_path = COOKIE_PATH_FOR_CURL;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $newUrl);
        curl_setopt($ch, CURLOPT_PORT, 80);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            "facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)"
        );
        // Add cookie path to curl
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_path);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_path);

        if ($parsedUrl['scheme'] == 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            if ($port == '80') {
                curl_setopt($ch, CURLOPT_PORT, 443);
            }
        }


        $result = curl_exec($ch);

        curl_close($ch);


        return $result;
    }


    function getTextAndTitle($rawData) : array
    {
        $finalArr = [$title = '',
            $text = ''];

        $colors = [
            'braun', 'anthrazit', 'grau', 'smoke', 'rot', 'blau', 'schwarz','grün', 'lachs', 'natur', 'rosa', 'kupfer' ,'purpurrot', 'gold', 'silbergrau', 'rotviolett', 'vintage braun'
        ];


        $descriptionNotReached = true;
        foreach ($rawData as $key => $fragment) {
            $fragment = trim($fragment);
            if (!preg_match('#\d#', $fragment) and !in_array(strtolower($fragment), $colors) and $descriptionNotReached == true or $key == 0) {
                if (empty($finalArr['title'])) {
                    $finalArr['title'] .= "{$fragment}";
                } else {
                    $finalArr['title'] .= ", {$fragment}";
                }

            } else {
                $descriptionNotReached = false;
                if (empty($finalArr['text'])) {
                    $finalArr['text'] .= "{$fragment}";
                } else {
                    $finalArr['text'] .= ", {$fragment}";
                }
            }
        }

        return $finalArr;
    }


}
