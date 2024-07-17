<?php

class Crawler_Company_Dm_WriteSnapchatSpreadsheet extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sGSWrite = new Marktjagd_Service_Output_GoogleSpreadsheetWrite();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $cArticles = $sApi->getActiveArticleCollection($companyId)->getElements();
        $aBrochures = $sApi->findActiveBrochuresByCompany($companyId);
        foreach ($aBrochures as $eBrochure) {
            if (!preg_match('#discover#', $eBrochure['brochureNumber'])) {
                continue;
            }
            $layout = json_decode($eBrochure['layout'], TRUE);
        }

        $aDiscoverArticles = [];
        foreach ($layout[4]['pages'] as $page) {
            foreach ($page['modules'] as $module) {
                if (!array_key_exists('products', $module)) {
                    continue;
                }
                foreach ($module['products'] as $product) {
                    $aDiscoverArticles[$product['id']] = $page['pageMetaphor'];
                }
            }
        }
        ksort($aDiscoverArticles);

        $aArticles[] = preg_split('#\s*;\s*#', 'id;title;description;availability;price;link;image_link;brand;custom_label_0');

        foreach ($cArticles as $eArticle) {
            if (!array_key_exists($eArticle->getArticleId(), $aDiscoverArticles)) {
                continue;
            }

            $singleArticle = [
                $eArticle->getArticleNumber(),
                $eArticle->getTitle(),
                $eArticle->getText(),
                'in stock',
                $eArticle->getPrice(),
                $eArticle->getUrl(),
                preg_replace('#_x\.$#', '', $eArticle->getImage()) . '_510x510_fillFFFFFF.png',
                $eArticle->getManufacturer(),
                $aDiscoverArticles[$eArticle->getArticleId()],
            ];

            foreach ($singleArticle as &$attribute) {
                if (is_null($attribute)) {
                    $attribute = '';
                }
            }

            $aArticles[] = $singleArticle;
        }

        $sGSWrite->writeGoogleSpreadsheet($aArticles, '1orLYRd8nZ9SqGhTq2VWAZhxNvbGj8AH6TkmQ003PVtM', FALSE, 'A1', 'snapchat_' . date('W_Y'), FALSE, TRUE);
    }
}