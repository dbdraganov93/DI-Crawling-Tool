<?php
/**
 * Generic brochure crawler for copying links
 */

class Crawler_Company_Offerista_Brochure extends Crawler_Generic_Company
{

    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $sFtp->connect($companyId, true);

        $templateFile = '';
        $aFilesToLink = [];

        foreach ($sFtp->listFiles('./template') as $singleRemoteFile) {
            if (preg_match('#\.pdf$#', $singleRemoteFile)) {
                $templateFile = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                break;
            }
        }

        if (!strlen($templateFile)) {
            throw new Exception($companyId . ': template file not found. Upload the file to the "template"-folder.');
        }

        foreach ($sFtp->listFiles('./toLink') as $singleRemoteFile) {
            if (preg_match('#([^\.\/]+)\.pdf$#', $singleRemoteFile, $nameMatch)) {
                $aFilesToLink[$nameMatch[1]] = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
                break;
            }
        }

        if (!count($aFilesToLink)) {
            throw new Exception($companyId . ': file(s) to link not found. Upload the file(s) to the "toLink"-folder.');
        }

        $sPdf = new Marktjagd_Service_Output_Pdf();

        $count = 0;
        foreach ($aFilesToLink as $fileName => $filePath) {
            if ($aFilesToLink[$fileName] = $sPdf->copyLinks($templateFile, [$filePath])[0]) {
                $this->_logger->info($companyId . ': file ' . $fileName . ' linked successfully.');
                $count++;
                $sFtp->upload($aFilesToLink[$fileName], '/' . $companyId . '/linked/' . basename($aFilesToLink[$fileName]));
            }
        }

        $sFtp->close();

        if (count($aFilesToLink) != $count) {
            throw new Exception($companyId . ': not all files could be linked.');
        }

        $this->_response->setLoggingCode(Crawler_Generic_Response::SUCCESS_NO_IMPORT)
            ->setIsImport(false);

        return $this->_response;
    }
}