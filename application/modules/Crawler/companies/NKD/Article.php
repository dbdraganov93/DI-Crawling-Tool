<?php

/**
 * Article crawler for NKD (ID: 342)
 */

class Crawler_Company_NKD_Article extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $feedUrl = 'https://www.semtrack.de/e?i=733ec7eadcd806e1847ad12c160b5aa7f0def89f';
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPdf = new Marktjagd_Service_Output_Pdf();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $aStores = $sApi->findStoresWithBrochures(342);
        $aStoreNumbers = [];
        foreach ($aStores as $singleStore) {
            $aStoreNumbers[] = $singleStore['number'];
        }

        $week = 'this';
        $localPath = $sFtp->connect($companyId, TRUE);

        $localBrochurePath = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#KW[_|\-|\s*]?' . date('W', strtotime($week . ' week')) . '#', $singleFile)) {
                $localBrochurePath = $sFtp->downloadFtpToDir($singleFile, $localPath);
                $sFtp->close();
                break;
            }
        }

        if (!$localBrochurePath) {
            throw new Exception($companyId . ': no brochure uploaded.');
        }

        $aInfos = $sPdf->getAnnotationInfos($localBrochurePath);

        $aArticlesToAdd = [];
        foreach ($aInfos as $clickoutInfos) {
            if (!is_null($clickoutInfos->url) && preg_match('#www\.nkd\.com\/(\d+)#', $clickoutInfos->url, $articleNumberMatch)) {
                $aArticlesToAdd[$articleNumberMatch[1]] = $clickoutInfos->page + 1;
            }
        }

        $ch = curl_init($feedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);

        $fileName = 'nkd.csv';
        $filePath = $localPath . $fileName;

        $fh = fopen($filePath, 'w+');
        fputs($fh, $result);
        fclose($fh);

        $fh = fopen($filePath, 'r');
        $aHeader = [];
        $aData = [];
        while (($data = fgetcsv($fh, 0, ';')) == TRUE) {
            if (!count($aHeader)) {
                $aHeader = $data;
                continue;
            }
            $aData[] = array_combine($aHeader, $data);
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleRow) {
            if (!array_key_exists(trim($singleRow['item_group_id']), $aArticlesToAdd)) {
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setTitle($singleRow['title'])
                ->setText(preg_replace('#(\s{2,}|\n|\t)#', '<br/>', $singleRow['description']))
                ->setUrl($singleRow['link'] . '?utm_source=offerista&utm_medium=cpc&utm_campaign=digitaler-prospekt-KW'
                    . date('W', strtotime($week . ' week')) . '&utm_content=seite-'
                    . str_pad($aArticlesToAdd[$singleRow['item_group_id']], 2, '0', STR_PAD_LEFT)
                    . '-productlisting&utm_product=' . strtolower(urlencode($singleRow['title'])) . '_' . $singleRow['item_group_id'])
                ->setImage($singleRow['image_link'])
                ->setArticleNumber($singleRow['item_group_id'])
                ->setPrice(preg_replace('#\s*EUR#', '', $singleRow['price']))
                ->setSuggestedRetailPrice($singleRow['sale_price'])
                ->setStart(date('d.m.Y', strtotime('thursday ' . $week . ' week')))
                ->setEnd(date('d.m.Y', strtotime('tuesday ' . $week . ' week + 1 weeks')))
                ->setVisibleStart(date('d.m.Y', strtotime('wednesday ' . $week . ' week')))
                ->setStoreNumber(implode(',', $aStoreNumbers));

            $cArticles->addElement($eArticle);
        }

        return $this->getResponse($cArticles);
    }
}