<?php

/*
 * Article Crawler für Office 4 Sale (ID: 71795)
 */

class Crawler_Company_Office4Sale_Article extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $baseUrl = 'https://www.bueroschnaeppchen.de/';
        $articleCategorieUrl = $baseUrl . 'export/';
        $sExcel = new Marktjagd_Service_Input_PhpExcel();

        $aCategories = array(
            'milando.txt'
        );

        $aArticleFiles = array();

        foreach ($aCategories as $singleCategory) {
            $categoryFileName = APPLICATION_PATH . '/../public/files/http/' . $companyId . '/' . preg_replace('#(txt)#', 'csv', $singleCategory);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $articleCategorieUrl . $singleCategory);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $stream = curl_exec($ch);
            $file = fopen($categoryFileName, 'w');
            fwrite($file, $stream);
            fclose($file);

            curl_close($ch);

            $aArticleFiles[] = $categoryFileName;
        }

        $aData = array();
        foreach ($aArticleFiles as $singleArticleFile) {
            $aSingleData = $sExcel->readFile($singleArticleFile, FALSE, ';')->getElement(0)->getData();
            $aData = array_merge($aData, $aSingleData);
        }

        $cArticles = new Marktjagd_Collection_Api_Article();
        foreach ($aData as $singleArticle) {
            if (!preg_match('#^\d#', $singleArticle[2])) {
                continue;
            }

            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setTitle($singleArticle[0])
                    ->setText($singleArticle[1])
                    ->setPrice($singleArticle[2])
                    ->setUrl($singleArticle[3])
                    ->setImage($singleArticle[4])
                    ->setTags($singleArticle[14])
                    ->setArticleNumber($singleArticle[11])
                    ->setManufacturer($singleArticle[12]);


            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
