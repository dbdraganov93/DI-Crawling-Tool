<?php
/**
 * Artikelcrawler für Europafoto 28984
 */
class Crawler_Company_Europafoto_Article extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $cArticle = new Marktjagd_Collection_Api_Article();
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        if (!$sFtp->connect($companyId)){
            throw new Exception($companyId . ': cannot connect to MJ-FTP');
        }

        $locFolderName = $sFtp->generateLocalDownloadFolder($companyId);
        $fileNameLocal = $sFtp->downloadFtpToDir('linkliste.xls', $locFolderName);
        
        // Konvertiert die Datei ins UTF-8 Format
        $sPhpExcel = new Marktjagd_Service_Input_PhpExcel();
        $xlsFile = $sPhpExcel->readFile($fileNameLocal, true);
        $worksheets = $xlsFile->getElements();
        $worksheet = $worksheets[0];

        foreach ($worksheet->getData() as $line) {
            // Farbe (teilweise mit : getrennt oder haben einen : am Ende stehen):
            $link = $line['Link'];
            if ($link == ''
                || $link == 'http://www.europafoto.de/hardware/canon-lens-cashback' 
            ) {
                continue;
            }

            $sPage = new Marktjagd_Service_Input_Page();

            $sPage->open($link);
            $page = $sPage->getPage()->getResponseBody();
            
            $pattern = '#<div\s*class="product\-name"[^>]*>\s*<h1>\s*(.*?)\s*</h1>#';
            if (!preg_match($pattern, $page, $title))
            {
                $this->_logger->err($companyId . ': unable to get article title on site: ' . $link);
            }
            
            $pattern = '#<div\s*class="description"[^>]*>\s*(.*?)\s*</div>#';
            if (!preg_match($pattern, $page, $description))
            {
                $this->_logger->warn($companyId . ': unable to get article description on site: ' . $link);
            }
            
            $pattern = '#<span\s*class="regular\-price"\s*id="product\-price\-[0-9]*"\s*>\s*<span\s*class="price"[^>]*>\s*(.*?)\s*€\s*</span>#';
            $price = array();
            if (!preg_match($pattern, $page, $price)
                || !count($price)) {
                $this->_logger->err($companyId . ': unable to get article price on site: ' . $link);
            }
                       
            $sNumbers = new Marktjagd_Service_Text_Numbers();
            $price = $sNumbers->normalizePrice($price[1]);
            
            $pattern = '#<th[^>]*>\s*Hersteller</th>\s*<td[^>]*>\s*<p[^>]*>[^<]*</p>'
                    . '\s*(.*?)\s*</td>#';
            if (!preg_match($pattern, $page, $manufacturer))
            {
                $this->_logger->warn($companyId . ': unable to get manufacturer on site: ' . $link);
            }
            
            $pattern = '#<th[^>]*>\s*Hersteller\s*EAN</th>\s*<td[^>]*>\s*<p[^>]*>[^<]*</p>'
                    . '\s*(.*?)\s*</td>#';
            if (!preg_match($pattern, $page, $ean))
            {
                $this->_logger->warn($companyId . ': unable to get ean on site: ' . $link);
            }
            
            $pattern = '#/hardware/(.*?)\.html#';
            if (!preg_match($pattern, $link, $articleNumber))
            {
                $this->_logger->warn($companyId . ': unable to get articleNumber for link: ' . $link);
            }
            $articleNumber = substr(md5($articleNumber[1]), 0, 30);
            
            $pattern = '#<div\s*class="productimage"[^>]*>.*?<img\s*id="product\-image".*?src="(.*?)"#';
            if (!preg_match($pattern, $page, $image))
            {
                $this->_logger->warn($companyId . ': unable to get article image on site: ' . $link);
            }
                        
            $eArticle = new Marktjagd_Entity_Api_Article();

            $eArticle->setArticleNumber($articleNumber);
            $eArticle->setTitle($title[1]);
            $eArticle->setPrice($price);
            $eArticle->setText($description[1]);
            $eArticle->setManufacturer($manufacturer[1]);
            $eArticle->setEan($ean[1]);
            $eArticle->setUrl($link);
            $eArticle->setImage($image[1]);
            
            $eArticle->setStart('12.09.2015');
            $eArticle->setVisibleStart('11.09.2015');
            $eArticle->setTags('Foto,Kamera,Objektiv,Stativ,Fotoapparat');
            $eArticle->setDistribution('KampagneSeptember2015');
            $cArticle->addElement($eArticle);
        }

        $sCsv = new Marktjagd_Service_Output_MarktjagdCsvArticle($companyId);
        $fileName = $sCsv->generateCsvByCollection($cArticle);
        return $this->_response->generateResponseByFileName($fileName);
    }
}
