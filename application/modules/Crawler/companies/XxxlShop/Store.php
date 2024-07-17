<?php

/*
 * Store Crawler für XXXL Möbelhäuser (u.a. ID: 80)
 */

class Crawler_Company_XxxlShop_Store extends Crawler_Generic_Company
{
    public function crawl($companyId): Crawler_Generic_Response
    {
        $sPage = new Marktjagd_Service_Input_Page();
        $cStores = new Marktjagd_Collection_Api_Store();
        $searchUrl = 'https://www.xxxlutz.de/api/graphql';

        $storeIds = $this->getStoreIds($companyId, $searchUrl, $sPage);

        foreach ( $storeIds as $storeId) {
            $this->_logger->info('Scraping ' . $storeId);
            $data = '{"operationName":"contentPages","variables":{"code":"' . $storeId. '","cmsTicketId":null},"query":"query contentPages($code: String!, $cmsTicketId: String) {\n  getContentPage(code: $code, cmsTicketId: $cmsTicketId) {\n    uid\n    code\n    name\n    restType\n    title\n    name\n    seoData {\n      canonicalUrl\n      code\n      description\n      keywords\n      name\n      noFollow\n      noIndex\n      url\n      title\n      seoPath\n      __typename\n    }\n    cmsPageType {\n      code\n      type\n      __typename\n    }\n    breadcrumbs {\n      itemCode\n      name\n      restType\n      seoUrl\n      type\n      __typename\n    }\n    smartedit {\n      pageContract\n      __typename\n    }\n    contentSlots\n    __typename\n  }\n}\n"}';
            $jData = $sPage->getJsonFromGraphQL($searchUrl, $data)->data->getContentPage;

            $url = $sPage->getJsonFromGraphQL($searchUrl, $data)->data->getContentPage->seoData->url;
            $this->_logger->info("url:\t\t" . $url);
            $title = $sPage->getJsonFromGraphQL($searchUrl, $data)->data->getContentPage->seoData->title;
            $this->_logger->info("title:\t\t" . $title);
            $imgUrl = $sPage->getJsonFromGraphQL($searchUrl, $data)->data->getContentPage->contentSlots[0]->components[1]->components[0]->image->url;
            $this->_logger->info("imgUrl:\t\t" . $imgUrl);
            $description = $sPage->getJsonFromGraphQL($searchUrl, $data)->data->getContentPage->seoData->description;
            $this->_logger->info("description:\t" . $description);

            $jDataEncoded = json_encode($jData);

            preg_match('#<strong>\s*adresse([^"]+?)"#i', $jDataEncoded, $matches);

            $tmp = preg_replace('#/>#', '', $matches[1]);
            $tmp = preg_replace('#\\\\#', '', $tmp);
            $tmp = preg_split('#<br#', $tmp);

            # Problem: Die Adresse kann 2 oder 3 Zeilen lang sein
            # Beispiel: xxxlutz-muellerland-hennef (2 Zeilen), xxxlutz-pallen (3 Zeilen)
            # wir testen, ob in tmp[4] eine PLZ zu finden ist , falls ja passen wir das Array an
            if(preg_match("#\d{5}#",$tmp[4])) {
                $tmp[2] = $tmp[3];
                $tmp[3] = $tmp[4];
            }

            $this->_logger->info("street:\t" . $tmp[2]);
            $this->_logger->info("city:\t" . $tmp[3]);

            $storeHoursRaw = $sPage->getJsonFromGraphQL($searchUrl, $data)->data->getContentPage->contentSlots[0]->components[1]->components[1]->components[1]->components[1]->components[0]->content;

            $eStore = new Marktjagd_Entity_Api_Store();
            $eStore->setTitle($title)
                ->setWebsite($url)
                ->setImage($imgUrl)
                ->setText($description)
                ->setStreetAndStreetNumber(preg_replace('#u00.+?$#', '', $tmp[2]))
                ->setZipcodeAndCity(preg_replace('#u00.+?$#', '', $tmp[3]))
                ->setStoreHoursNormalized($storeHoursRaw);

            if (!$cStores->addElement($eStore)) {
                $this->_logger->info('Failed to add ' . $storeId);
            }

            sleep(1);
        }

        return $this->getResponse($cStores);
    }

    /**
     * @param string $companyId
     * @param string $searchUrl
     * @param Marktjagd_Service_Input_Page $sPage
     * @return array
     * @throws Exception
     */
    private function getStoreIds(string $companyId, string $searchUrl, Marktjagd_Service_Input_Page $sPage): array
    {
        $this->_logger->info('Querying available store ids');
        $data = '{"operationName":"contentPages","variables":{"code":"filialen-standorte","cmsTicketId":null},"query":"query contentPages($code: String!, $cmsTicketId: String) {\n  getContentPage(code: $code, cmsTicketId: $cmsTicketId) {\n    uid\n    code\n    name\n    restType\n    title\n    name\n    seoData {\n      canonicalUrl\n      code\n      description\n      keywords\n      name\n      noFollow\n      noIndex\n      url\n      title\n      seoPath\n      __typename\n    }\n    cmsPageType {\n      code\n      type\n      __typename\n    }\n    breadcrumbs {\n      itemCode\n      name\n      restType\n      seoUrl\n      type\n      __typename\n    }\n    smartedit {\n      pageContract\n      __typename\n    }\n    contentSlots\n    __typename\n  }\n}\n"}';
        $jData = $sPage->getJsonFromGraphQL($searchUrl, $data);

        $aStoreIds = [];
        foreach ($jData->data->getContentPage->contentSlots[0]->components as $item) {
            if (!isset($item->components)) {
                continue;
            }

            foreach ($item->components as $component) {
                if (isset($component->link->url) && preg_match('#/c/(.+)$#i', $component->link->url, $match)) {
                    $aStoreIds[] = $match[1];
                }
            }
        }
        if (!$aStoreIds) {
            throw new Exception($companyId . ': unable to get any store detail urls.');
        }

        $this->_logger->info('FOUND ' . count($aStoreIds) . 'store ids');
        return $aStoreIds;
    }

    /**
     * @param $searchUrl
     * @param $storeUrl
     * @return string
     */
    private function getUrl($searchUrl, $storeUrl): string
    {
        $parsedUrl = parse_url($searchUrl);
        return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $storeUrl;
    }

}
