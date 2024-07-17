<?php

/**
 * Discover article crawler for Remember (ID: 82393)
 */
class Crawler_Company_Remember_DiscoverArticle extends Crawler_Generic_Company
{
    private Marktjagd_Service_Transfer_FtpMarktjagd $sFtp;

    public function crawl($companyId)
    {
        $aCampaigns = [
            1 => [
                'valid_start' => '28.11.2022',
                'valid_end' => '31.12.2022',
                'article_file_name' => 'Remember Produktauswahl Offerista_new.xlsx'
            ]
        ];

        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $cArticles = new Marktjagd_Collection_Api_Article();

        $this->sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $localPath = $this->sFtp->connect($companyId, TRUE);

        foreach ($aCampaigns as $singleCampaign) {

            $localArticleFile = $this->getLocalArticleFile($singleCampaign['article_file_name'], $localPath);

            $aData = $sPss->readFile($localArticleFile, TRUE)->getElement(0)->getData();

            foreach ($aData as $singleRow) {
                $eArticle = $this->createArticle($singleRow, $singleCampaign);

                $cArticles->addElement($eArticle);
            }
        }

        $this->sFtp->close();

        return $this->getResponse($cArticles);
    }

    private function getLocalArticleFile(string $fileName, string $localPath): string
    {
        foreach ($this->sFtp->listFiles('./Discover') as $singleRemoteFile) {
            if (preg_match('#' . $fileName . '$#', $singleRemoteFile)) {
                return $this->sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }

        return '';
    }

    private function createArticle(array $details, array $campaign): Marktjagd_Entity_Api_Article
    {
        $eArticle = new Marktjagd_Entity_Api_Article();

        $eArticle->setArticleNumber($details['article_number'] . '_Disc')
            ->setTitle($details['title'])
            ->setText($details['text'])
            ->setImage($details['image'])
            ->setSuggestedRetailPrice($details['suggested_retail_price'])
            ->setPrice($details['price'])
            ->setUrl($details['URL'])
            ->setStart($campaign['valid_start'])
            ->setEnd($campaign['valid_end'])
            ->setVisibleStart($campaign['valid_start']);

        if (empty($eArticle->getImage())) {
            if (!empty($eArticle->getUrl())) {
                $sPage = new Marktjagd_Service_Input_Page();
                $sPage->open($eArticle->getUrl());
                $page = $sPage->getPage()->getResponseBody();
                $pattern = '#<img[^>]*src="([^"]+?)"[^>]*alt="\s*' . $eArticle->getTitle() . '\s*"#';

                if (preg_match($pattern, $page, $imageMatch)) {
                    $eArticle->setImage($imageMatch[1]);
                }
            }
        }

        return $eArticle;
    }
}