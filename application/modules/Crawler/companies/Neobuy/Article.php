<?php
/**
 * Artikelcrawler für Neobuy (ID: 29172)
 */
class Crawler_Company_Neobuy_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @throws Exception
     * @return Crawler_Generic_Response
     */
    public function crawl($companyId)
    {
        $sDownload = new Marktjagd_Service_Transfer_Download();
        $sHttp = new Marktjagd_Service_Transfer_Http();
        $downloadPath = $sHttp->generateLocalDownloadFolder($companyId);
        $downloadPathFile = $sDownload->downloadByUrl(
            'http://www.neobuy.de/csv-maker/export/marktjagd.csv',
            $downloadPath);

        $sCsv = new Marktjagd_Service_Input_Csv();
        $delimiter = $sCsv->findDelimiter($downloadPathFile);

        $sExcel = new Marktjagd_Service_Input_PhpExcel();
        $worksheet = $sExcel->readFile($downloadPathFile, true, $delimiter);

        $worksheet = $worksheet->getElement(0);
        /* @var $worksheet Marktjagd_Entity_PhpExcel_Worksheet */
        $lines = $worksheet->getData();

        $cArticle = new Marktjagd_Collection_Api_Article();
        foreach ($lines as $line) {
            $eArticle = new Marktjagd_Entity_Api_Article();

            $articleText = $line['beschreibung'];

            // remove / replace invalid tags
            $cleanSearch = array(
                '#<hr[^>]*width[^>]*>#',
                '#<\\/?div[^>]*>#',
                '#<\\/?tbody[^>]*>#',
                '#<\\/?table[^>]*>#',
                '#<\\/?tr[^>]*>#',
                '#<td[^>]*>#',
                '#<\\/td[^>]*>#',
                '#<style[^>]*>.+?</style>#'
            );

            $cleanReplace = array(
                '',
                '',
                '',
                '',
                '',
                '<p>',
                '</p>',
                ''
            );

            $articleText = preg_replace($cleanSearch, $cleanReplace, $articleText);

            $eArticle->setArticleNumber(trim($line['id']))
                     ->setTitle($line['title'])
                     ->setText($articleText)
                     ->setUrl($line['link'])
                     ->setImage($line['bildlink'])
                     ->setTrademark($line['marke'])
                     ->setPrice($line['preis'])
                     ->setEan($line['gtin'])
                     ->setArticleNumberManufacturer($line['mpn'])
                     ->setTags(preg_replace('# &#', ',', $line['Produkttyp']));

            $cArticle->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        $this->_response->generateResponseByFileName($fileName);
        return $this->_response;
    }
}