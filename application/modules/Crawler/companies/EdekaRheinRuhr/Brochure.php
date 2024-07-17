<?php

/*
 * Prospekt Crawler für Rhein Ruhr Edeka Vertriebslinien (72178 - 72179, 72180)
 */

class Crawler_Company_EdekaRheinRuhr_Brochure extends Crawler_Generic_Company
{

    protected $title;
    protected $aStoresToAssign = [];

    public function crawl($companyId)
    {
        $duplicateBrochure = [72178, 72180];

        $sFtp = new Marktjagd_Service_Transfer_FtpMarktjagd();
        $sPss = new Marktjagd_Service_Input_PhpSpreadsheet();
        $sApi = new Marktjagd_Service_Input_MarktjagdApi();

        $this->title = $sApi->findCompanyByCompanyId($companyId)["title"];

        $week = 'next';
        if (date('w', strtotime('now')) < 4
            || (date('w', strtotime('now')) == 4
                && date('H', strtotime('now')) <= 11)) {
            $week = 'this';
        }

        $weekNo = date('W', strtotime($week . ' week'));
        $year = date('Y', strtotime($week . ' week'));

        $aDists = [
            '22241' => 'trinkgut',
            '72178' => 'EDEKA',
            '72179' => 'Marktkauf',
            '72180' => 'E center'
        ];

        $localPath = $sFtp->connect('72178', TRUE);

        $localAssignmentFile = '';
        foreach ($sFtp->listFiles() as $singleFile) {
            if (preg_match('#Marktliste[^\.]+?KW' . $weekNo . '-' . $year . '\.xlsx?#', $singleFile)) {
                $this->_logger->info($companyId . ': assignment file found.');
                $localAssignmentFile = $sFtp->downloadFtpToDir($singleFile, $localPath);
                break;
            }

        }

        $aAssignmentData = $sPss->readFile($localAssignmentFile, TRUE)->getElement(0)->getData();

        $this->aStoresToAssign = [];
        foreach ($aAssignmentData as $singleRow) {
            if (!preg_match('#' . $aDists[$companyId] . '#', $singleRow['VERTRIEBSSCHIENE'])) {
                continue;
            }
            $this->aStoresToAssign[str_pad($singleRow['WERBEGEBIET_HZ'], 4, '0', STR_PAD_LEFT)][] = $singleRow['MARKT_ID'];
        }

        $pattern = '#KW' . $weekNo . '[^\.]+_(\d{2})(\d{2})(\d{2})_RHEINRUHR_([^_\.]+?)\.pdf#';
        if ($companyId == 72179) {
            $pattern = '#KW' . $weekNo . '[^\.]+_(\d{2})(\d{2})(\d{2})_(MARKTKAUF_[^_\.]+?)\.pdf#';
        }

        $aBrochuresToAssign = [];
        foreach ($sFtp->listFiles() as $singleFile) {
            if (!preg_match($pattern, $singleFile, $distMatch)
                && !preg_match('#KW' . $weekNo . '_(\d{2})(\d{2})(\d{2})[^\.]*(Tageszeitung)\.pdf#', $singleFile, $distMatch)) {
                $this->_logger->info($companyId . ': ' . $singleFile . ' doesn\'t have a valid scheme. skipping...');
                continue;
            }
            if (strtotime('now') > strtotime($distMatch[1] . '.' . $distMatch[2] . '.20' . $distMatch[3])) {
                $this->_logger->info($companyId . ': ' . $singleFile . ' not valid anymore. skipping...');
                continue;
            }

            if (array_key_exists($distMatch[4], $this->aStoresToAssign)
                || preg_match('#Tageszeitung#', $distMatch[4])) {
                $aBrochuresToAssign[$distMatch[4]] = $sFtp->downloadFtpToDir($singleFile, $localPath);
            }
        }

        $sFtp->close();

        $cBrochures = new Marktjagd_Collection_Api_Brochure();
        $uniqueBrochure = 0;
        foreach ($aBrochuresToAssign as $distribution => $filePath) {
            if (date('w', strtotime('now')) < 4
                && !preg_match('#Tageszeitung#', $distribution)) {
                continue;
            }
            if (!preg_match('#(\d{2})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})#', $filePath, $visibilityMatch)) {
                $this->_logger->err($companyId . ': unable to get brochure visibility');
                continue;
            }
            $filePathCopy = preg_replace('#.pdf#', '_DLC.pdf', $filePath);
            copy($filePath, $filePathCopy);

            if ($cBrochures->addElement($this->addBrochure($filePath, $visibilityMatch, $distribution))) {
                $uniqueBrochure++;
            }
            if (in_array($companyId, $duplicateBrochure)) {
                // Duplicate brochures and add prefix to brochure name.
                $cBrochures->addElement($this->addBrochure($filePathCopy, $visibilityMatch, $distribution, 'DLC_'));
            }
        }

        //if (count($aBrochuresToAssign) * 0.75 > $uniqueBrochure) {
        //    throw new Exception($companyId . ': Too less elements in brochure collection.');
        //}

        return $this->getResponse($cBrochures);
    }

    protected function addBrochure($filePath, $visibilityMatch, $distribution, $prefix = '')
    {
        $eBrochure = new Marktjagd_Entity_Api_Brochure();
        $eBrochure->setTitle($this->title . ': Wochenangebote')
            ->setUrl($filePath)
            ->setVisibleStart($visibilityMatch[1] . '.' . $visibilityMatch[2] . '.20' . $visibilityMatch[3])
            ->setEnd($visibilityMatch[4] . '.' . $visibilityMatch[5] . '.20' . $visibilityMatch[6])
            ->setStart(date('d.m.Y', strtotime($eBrochure->getVisibleStart() . '+1 day')))
            ->setBrochureNumber($prefix . $distribution . '_KW' . date('W', strtotime($eBrochure->getStart())))
            ->setStoreNumber(implode(',', $this->aStoresToAssign[$distribution]))
            ->setVariety('leaflet');

        if (strtotime($eBrochure->getStart()) == strtotime('25.12.' . date('Y', strtotime('this year')))
            || strtotime($eBrochure->getStart()) == strtotime('26.12.' . date('Y', strtotime('this year')))) {
            $eBrochure->setStart('27.12.' . date('Y', strtotime('this year')));
        }

        if (preg_match('#Tageszeitung#', $distribution)) {
            $eBrochure->setStart($eBrochure->getVisibleStart());
        }

        return $eBrochure;
    }
}
