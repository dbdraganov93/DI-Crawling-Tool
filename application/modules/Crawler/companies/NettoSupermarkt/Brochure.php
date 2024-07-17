<?php

/**
 * Prospektcrawler für Netto Supermarkt (ID: 73)
 */
class Crawler_Company_NettoSupermarkt_Brochure extends Crawler_Generic_Company
{
    /**
     * @param int $companyId
     * @return Crawler_Generic_Response
     * @throws Exception
     */
    public function crawl($companyId)
    {
        $cBrochure = new Marktjagd_Collection_Api_Brochure();
        $sMarktjagdApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTimes = new Marktjagd_Service_Text_Times();

        $week = 'next';
        $nextWeek = $sTimes->getWeekNr($week);
        $year = $sTimes->getWeeksYear($week);

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sFtp->connect($companyId);

        $cDistributionApi = $sMarktjagdApi->findDistributionsByCompany($companyId);
        /* @var $eDistributionApi Marktjagd_Entity_Api_Distribution */
        foreach ($cDistributionApi->getElements() as $eDistributionApi) {
            if (preg_match('#Test#', $eDistributionApi->getTitle())) {
                continue;
            }
            $pattern = '#' . $nextWeek . '\_' . $year . '.*?\_(?:' . str_replace('-', '|', preg_replace(array('#BR$#', '#NS#'), array('BRB', 'N'), $eDistributionApi->getTitle())) . ')[\_|\.].*?pdf#i';
            $aPdfFiles = $sFtp->listFiles('.', $pattern);
            if (!count($aPdfFiles)) {
                $aPdfFiles = $sFtp->listFiles('.', '#' . $nextWeek . '\_' . $year . '\.pdf#i');
            }
            $pdfFile = $aPdfFiles[0];

            if (!strlen($pdfFile)) {
                $this->_logger->err('unable to get any brochure for distribution '
                    . $eDistributionApi->getTitle() . ' on FTP (73)');
                continue;
            }

            $localFile = $sFtp->downloadFtpToCompanyDir($pdfFile, $companyId);
            $eBrochure = new Marktjagd_Entity_Api_Brochure();
            $eBrochure->setTitle('Günstig sind wir sowieso.')
                ->setStart(date('Y-m-d', $sTimes->getBeginOfWeek($year, $nextWeek)))
                ->setEnd(date('Y-m-d', $sTimes->getEndOfWeek($year, $nextWeek)))
                ->setVisibleStart(date(
                    'Y-m-d', strtotime(
                    '-4 days', $sTimes->getBeginOfWeek($year, $nextWeek)
                )))
                ->setUrl($sFtp->generatePublicFtpUrl($localFile))
                ->setDistribution($eDistributionApi->getTitle())
                ->setBrochureNumber($eDistributionApi->getTitle() . '_' . $nextWeek . '_' . $year)
                ->setVariety('leaflet');

            $cBrochure->addElement($eBrochure, true);
        }

        return $this->getResponse($cBrochure, $companyId);
    }
}
