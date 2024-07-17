<?php

/**
 * Brochure Crawler für Edeka Südbayern OU (ID: 89954)
 */
class Crawler_Company_Edeka_BrochureOU extends Crawler_Generic_Company
{
    private const NEWSLETTER_PAGE = 'EDEKA Südbayern Newsletter Page.pdf';
    protected $_title;
    protected $_week;

    public function crawl($companyId)
    {
        $this->_week = 'next';

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();
        $sTime = new Marktjagd_Service_Text_Times();
        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $sPdf = new Marktjagd_Service_Output_Pdf();

        $this->_title = $sApi->findCompanyByCompanyId($companyId)["title"];
        $nextWeek = $sTime->getWeekNr($this->_week);
        $nextWeekYear = $sTime->getWeeksYear($this->_week);

        $cStores = $sApi->findStoresByCompany($companyId)->getElements();
        $aDistributions = [];
        foreach ($cStores as $eStore) {
            if (!in_array($eStore->getDistribution(), $aDistributions)) {
                $aDistributions[] = $eStore->getDistribution();
            }
        }

        $locFolder = $sFtp->generateLocalDownloadFolder($companyId);
        $sFtp->connect('72089');

        $newsletterPage = '';
        foreach ($sFtp->listFiles('.', '#\.pdf#') as $newsletter) {
            if (self::NEWSLETTER_PAGE === $newsletter) {
                $newsletterPage = $sFtp->downloadFtpToDir($newsletter, $locFolder);
                break;
            }
        }

        $sFtp->changedir('./KW' . $nextWeek . '_' . $nextWeekYear);
        $uniqueBrochure = 0;
        foreach ($sFtp->listFiles('.', '#\.pdf#') as $singleBrochure) {
            $pattern = '#KW(\d{2})_([0-9]{6,7})_([0-9]{6})_SUEDBAYERN_(.+?)(_NEU)?\.pdf#i';
            if (!preg_match($pattern, $singleBrochure, $validityMatch)) {
                $this->_logger->err($companyId . ': invalid name scheme: ' . $singleBrochure);
                continue;
            }
            $distributionToSet = '';

            foreach ($aDistributions as $singleDistribution) {
                if (preg_match('#' . $singleDistribution . '$#i', $validityMatch[4])) {
                    $distributionToSet = $singleDistribution;
                    break;
                }
            }

            if (!strlen($distributionToSet)) {
                continue;
            }

            $locFile = $sFtp->downloadFtpToDir($singleBrochure, $locFolder);
            $locFile = $sPdf->merge([$locFile, $newsletterPage], dirname($locFile));

            $startDate = preg_replace('#([0-9]{2})([0-9]{2})([0-9]{2,3})#', '$1.$2.2023', $validityMatch[2]);

            $eBrochure = $this->addBrochure($locFile, $startDate, $validityMatch, $distributionToSet);

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures, $companyId);
    }

    protected function addBrochure($ftpUrl, $startDate, $validityMatch, $distribution, $prefix = '')
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();

        $eBrochure->setUrl($ftpUrl)
            ->setStart(date('d.m.Y', strtotime($startDate . '+1 day')))
            ->setEnd(preg_replace('#([0-9]{2})([0-9]{2})([0-9]{2})#', '$1.$2.20$3', $validityMatch[3]))
            ->setDistribution($distribution)
            ->setTitle($this->_title . ': Wochenangebote')
            ->setVisibleStart($startDate)
            ->setVariety('leaflet')
            ->setBrochureNumber($prefix . $validityMatch[4] . '_' . $validityMatch[1] . '_' . $validityMatch[2]);

        return $eBrochure;
    }
}
