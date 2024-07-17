<?php

/**
 * Article Crawler for EdekaSW Discover (ID: 2)
 * Based on Edeka API 👍
 */

class Crawler_Company_EdekaSW_DiscoverArticle extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $aStores = [
            1 => ['stores' => ['8000834','246196','19165','8000182','8000039','10002517','10001891','8000909','246210','5549182','8002841']],
            2 => ['stores' => ['8002321','6281104','349382','5541504','10002020','90939']],
            3 => ['stores' => ['109209']],
            4 => ['stores' => ['8002069', '50','5008','8001456','191742','8003006','10094','10000939','83718','17134','349513','21726','6061386','20645','192547','8002881','8000297','60037','8002880','8001706','5191','93287','10003026','3840892','597205','4538078','10000940','10229','8000511','109180','8000498','1234568','84881','2712576','8003009','10001606','243892','10003045','8000008','10003042']]
        ];

        $token = $this->get_api_access_token();
        $articles = $this->get_article_page($token['access_token'], 1000);

        $categories = [];
        foreach ($articles->offers as $article) {
            if (!array_key_exists($article->category->name, $categories)) {
                $categories[$article->category->name] = [];
            }

            $categories[$article->category->name][] = $article->id;
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articles->offers as $article) {
            $eArticle = new Marktjagd_Entity_Api_Article();
            $eArticle->setTitle(trim($article->title))
                ->setArticleNumber($article->id)
                ->setPrice($article->price->value);

            $article_description = '';
            foreach ($article->descriptions as $description) {
                $article_description = $article_description . $description . ',';
            }

            $article_description = preg_replace('#,$#', '', $article_description);
            $eArticle->setText($article_description);

            $eArticle->setStart($article->validFrom)
                ->setEnd($article->validTill)
                ->setVisibleStart(date('d.m.Y', strtotime($eArticle->getStart() . ' - 1 day')))
                ->setImage($article->image->imageUrl)
                ->setDistribution('Kopernikus');

            if (in_array($article->id, $categories[$article->category->name])) {
                $article_description = '';
                foreach ($article->descriptions as $description) {
                    $article_description = $article_description . $description . ',';
                }

                $article_description = preg_replace('#,$#', '', $article_description);
                foreach ($aStores as $storesKey => $aInfos) {
                    $eArticle = new Marktjagd_Entity_Api_Article();

                    $eArticle->setTitle(trim($article->title))
                        ->setPrice($article->price->value)
                        ->setStart($article->validFrom)
                        ->setEnd($article->validTill)
                        ->setVisibleStart(date('d.m.Y', strtotime($eArticle->getStart() . ' - 1 day')))
                        ->setImage($article->image->imageUrl)
                        ->setText($article_description)
                        ->setStoreNumber(implode(",", $aInfos['stores']))
                        ->setArticleNumber($article->id . '_K' . $storesKey);
                    $cArticles->addElement($eArticle);
                }
            } else {
                $cArticles->addElement($eArticle);
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }

    /**
     * Queries Edekas OAuth 2 API to get an access token for their article api
     * @return array
     * @throws Exception
     */
    public
    function get_api_access_token(): array
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
    public
    function get_article_page($access_token, $pageSize = 500)
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
}
