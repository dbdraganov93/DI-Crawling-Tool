<?php

/**
 * NewGen Article Crawler für Decathlon (ID: 68079, stage: 77265)
 */

class Crawler_Company_Decathlon_NewGenArticle extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.decathlon.de/';
        $month = 'this';
        $sGS = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $sPage = new Marktjagd_Service_Input_Page();

        $aData = $sGS->getFormattedInfos('1v_hQ2Hx_rctlzVcn9t-fJBegvIXsIzRDmNO2R11eRhY', 'A1', 'E', 'aktuell');

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $product) {

            $page = $this->getProductInfo($product['URL']);

            preg_match('#({"@context".+?)(?=<)#', $page, $matched);
            $json = json_decode($matched[1]);
//            var_dump($json->offers[0][0]);die;
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setArticleNumber('Discover_' . $product['REFERENZ'])
                ->setTitle($product['PRODUCT NAME'])
                ->setText($json->description)
                ->setPrice($json->offers[0][0]->price)
                ->setImage($json->offers[0][0]->image)
                ->setUrl($product['url'])
                ->setStart('25.11.2021')
                ->setEnd('24.12.2021')
                ->setVisibleStart($eArticle->getStart());

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles, $companyId);
    }


    public function getProductInfo($url)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',

        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }

}
