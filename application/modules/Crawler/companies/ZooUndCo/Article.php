<?php

/*
 * Artikel Crawler für Zoo & Co (ID: 338)
 */

class Crawler_Company_ZooUndCo_Article extends Crawler_Generic_Company {

    public function crawl($companyId) {
        $baseUrl = 'http://www.zooundco24.de/';
        $feedUrl = $baseUrl . 'shop/?cl=cdd_marktjagd';
        $sPage = new Marktjagd_Service_Input_Page(TRUE);
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $aBrochures = $sApi->findActiveBrochuresByCompany($companyId);

        $articleVisibleStart = '';
        $articleVisibleEnd = '';

        foreach ($aBrochures as $singleBrochure) {
            if (!is_array($singleBrochure)) {
                continue;
            }
            $validFrom = new DateTime($singleBrochure['validFrom']);
            $validTo = new DateTime($singleBrochure['validTo']);
            if ((int) date_diff($validTo, $validFrom)->format('%a') < 14) {
                if ($validFrom->getTimestamp() < strtotime('now') && $validTo->getTimestamp() > strtotime('now')) {
                    $this->_response->setIsImport(FALSE)
                            ->setLoggingCode(4);

                    return $this->_response;
                } elseif ($validFrom->getTimestamp() > strtotime('now')) {
                    $articleVisibleEnd = date('d.m.Y', $validFrom->getTimestamp() - 86400);
                } elseif ($validTo->getTimestamp() < strtotime('now')) {
                    $articleVisibleStart = date('d.m.Y', $validTo->getTimestamp() + 86400);
                }
            }
        }

        $sPage->open($feedUrl);
        $page = $sPage->getPage()->getResponseBody();

        $aData = preg_split('#\n#', $page);
        $aHeadline = array_slice(preg_split('#;#', $aData[0]), 1, -1);

        $cArticles = new Marktjagd_Collection_Api_Article();
        for ($i = 1; $i < count($aData); $i++) {
            $aContent = array_slice(preg_split('#;#', $aData[$i]), 1, -1);
            if (!count($aContent)) {
                continue;
            }

            $aArticleData = array_combine($aHeadline, $aContent);

            $eArticle = new Marktjagd_Entity_Api_Article();
            foreach ($aArticleData as $key => $value) {
                $eArticle->{set . preg_replace('#_([a-z])#', ucwords('$1'), $key)}($value);
            }

            if (strlen($articleVisibleEnd)) {
                $eArticle->setVisibleEnd($articleVisibleEnd);
            }

            if (strlen($articleVisibleStart)) {
                $eArticle->setVisibleStart($articleVisibleStart);
            }

            $cArticles->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticles);

        return $this->_response->generateResponseByFileName($fileName);
    }

}
