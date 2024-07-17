<?php
require APPLICATION_PATH . '/../library/Marktjagd/Service/NewGen/Blender.php';

/**
 * NewGen Brochure Crawler für Holzleitner (ID: 81114)
 */
class Crawler_Company_Holzleitner_DiscoverBrochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sGSRead = new Marktjagd_Service_Input_GoogleSpreadsheetRead();
        $discoverProducts = $sGSRead->getFormattedInfos('1NSpG_9I0fHXooKETPMubxPekPpRHXkWS9QQx1MUOWuU', 'A1', 'N', 'RPuO39suKc7nImgO5BK9xA==');


        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $aApiData = $sApi->getActiveArticleCollection($companyId);
        $aArticleIds = [];
        foreach ($aApiData->getElements() as $eApiData) {
            $aArticleIds[$eApiData->getArticleNumber()] = $eApiData->getArticleId();
        }

        $aNewGen = [];
        foreach ($discoverProducts as $singleRow) {

            if(!$aArticleIds[$singleRow['article number']]) {
                $this->_logger->warn('fehlende Artikelnummer ' . $singleRow['article number']);
                continue;
            }

            $aNewGen[$singleRow['category']]['page_metaphore'] = $singleRow['category'];
            $aNewGen[$singleRow['category']]['products'][] = [
                'product_id' => $aArticleIds[$singleRow['article number']],
                'priority' => '1',
            ];

        }

        var_dump(array_values($aNewGen));


        $response = Blender::blendApi(81114, array_values($aNewGen));
        var_dump($response);
    }
}
