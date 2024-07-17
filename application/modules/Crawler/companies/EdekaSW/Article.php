<?php

/**
 * Article Crawler for EdekaSW (ID: 71668)
 * Based on Edeka API 👍
 */

class Crawler_Company_EdekaSW_Article extends Crawler_Generic_Company {
    public function crawl($companyId)
    {
        $token = $this->get_api_access_token();
        $articles = $this->get_article_page($token['access_token'], 1000);

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($articles->offers as $article) {
            foreach (get_object_vars($article) as $key => $property) {
                $keys[$key] = $key;
            }

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
                ->setVisibleStart($eArticle->getStart())
                ->setImage($article->image->imageUrl);

            if (!$cArticles->addElement($eArticle)){
                $this->_logger->info('Not able to add article');
            }
        }

        return $this->getResponse($cArticles, $companyId);
    }

    /**
     * Queries Edekas OAuth 2 API to get an access token for their article api
     * @return array
     * @throws Exception
     */
    public function get_api_access_token(): array
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
    public function get_article_page($access_token, $pageSize = 500)
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
