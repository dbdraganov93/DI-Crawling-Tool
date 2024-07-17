<?php

/**
 * Dynamic Brochure Crawler für KiK (ID: 340)
 */

class Crawler_Company_Kik_ConvertImages extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        # the paths - adjust as needed
        $localPath = APPLICATION_PATH . '/../public/files/ftp/340/';
        $ftpPath = 'OP1 KW6/Fotomaterial';
        $apiKey = 'EY8N5YG-RG1MJ11-Q29KJ77-4Q4QKQ4';

        # if the directory exists, delete and recreate it
        if (is_dir($localPath)) {
            exec('rm -r ' . $localPath );
        }

        $aArticleImages = $this->downloadImages($companyId, $localPath, $ftpPath);


        # we need to call the image service via curl
        foreach($aArticleImages as $aIndex => $singleImage) {
            $outputFile = str_replace('.jpg', '.png', $singleImage);
            $ret = system("curl -H 'X-API-Key: ".$apiKey."' -F 'image=@".$singleImage ."' -F 'mode=image' -F 'format=png' -F 'background_color=#FFFFFF' -f https://api.photoscissors.com/v1/change-background -o '".$outputFile."'");

            $this->_logger->info('created ' . $outputFile);
        }
    }


    /**
     * This method traverses the FTP-Folder and extracts images and the article list.
     * The files are downloaded to the localpath provided in the crawler.
     *
     * @param int $companyId
     * @param string $localPath
     * @param string $ftpPath
     * @return array[] $aArticleImages
     * @throws Exception
     */
    public function downloadImages(int $companyId, string $localPath, string $ftpPath = '.') : array
    {
        $ftpPaths = [$ftpPath];

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId, TRUE);

        $aArticleImages = [];

        # traverse all folders in ftpPaths array
        foreach($ftpPaths as $ftpPath) {
            if(!$sFtp->changedir($ftpPath)) {
                throw new Exception('Cannot change to sub-dir ' . $ftpPath);
            }

            # download every image there
            foreach ($sFtp->listFiles() as $singleFile) {

                if (preg_match('#\.(png|jpg)$#', $singleFile, $imageMatch)) {
                    $aArticleImages[$singleFile] = $sFtp->downloadFtpToDir($singleFile, $localPath);
                    #$this->_logger->info('Downloading ' . $singleFile);
                }
            }
            $sFtp->changedir('..');
        }

        $sFtp->close();
        $this->_logger->info('Image download completed');
        return $aArticleImages;
    }

    /**
     * This function reads all annotations from the template pdf and builds a template array
     * that can be filled with articles
     *
     * TODO: right now this only works for our specific flyer
     *
     * @param Marktjagd_Service_Output_Pdf $sPdf
     * @param string $localBrochurePath - Path to the template pdf
     * @return array[]
     * @throws Exception
     */
}