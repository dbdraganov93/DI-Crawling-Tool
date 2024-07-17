<?php
/**
 * Brochure crawler for mea Apotheken (ID: 71112)
 */

class Crawler_Company_MeaApotheke_Brochure extends Crawler_Generic_Company
{
    public function crawl($companyId)
    {
        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();

        $localPath = $sFtp->connect($companyId, TRUE);

        $aBrochures = [];
        foreach ($sFtp->listFiles() as $singleRemoteFile) {
            if (preg_match('#([^\.]+)\.pdf#', $singleRemoteFile, $storeNumberMatch)) {
                $aBrochures[(int)$storeNumberMatch[1]] = $sFtp->downloadFtpToDir($singleRemoteFile, $localPath);
            }
        }
        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        foreach ($aBrochures as $storeNumber => $localPath) {
            $eBrochure = new Marktjagd_Entity_Api_Brochure();

            $eBrochure->setStoreNumber($storeNumber)
                ->setUrl($localPath)
                ->setTitle('mea Apotheke: Unsere Angebote im Juli!')
                ->setStart('2024-07-01')
                ->setEnd('2024-07-31')
                ->setVisibleStart($eBrochure->getStart())
                ->setBrochureNumber($storeNumber . date('_m_Y', strtotime($eBrochure->getStart())));

            $cBrochures->addElement($eBrochure);
        }

        return $this->getResponse($cBrochures);
    }
}
